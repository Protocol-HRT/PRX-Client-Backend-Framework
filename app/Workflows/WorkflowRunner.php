<?php

namespace App\Workflows;

use App\Cms\Support\VisibleWhen;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowActionRun;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Evaluates one workflow against one context, and runs its actions.
 *
 * EVERY EVALUATION IS RECORDED, matched or not. A skipped run costs a row and
 * answers the only question operators ever ask about a workflow that appears
 * broken, which is why the conditions are re-checked field by field on failure to
 * produce a human `skip_reason` rather than a bare "did not match".
 *
 * Actions run IN ORDER and a failure does not abort by default. A CRM push that
 * fails must not prevent the disposition update behind it — the operator decides
 * per action with `halt_on_failure`.
 */
class WorkflowRunner
{
    public function __construct(private readonly WorkflowRegistry $registry) {}

    public function run(Workflow $workflow, WorkflowContext $context): WorkflowRun
    {
        $conditions = $workflow->conditions ?? [];

        if (! VisibleWhen::passes($conditions, $context->accessor())) {
            return $this->record($workflow, $context, WorkflowRun::STATUS_SKIPPED, [
                'skip_reason' => $this->explainSkip($conditions, $context),
            ]);
        }

        // RUNNING, not COMPLETED. A row created as completed before any work
        // happens is a row that lies if the process dies mid-run, and the only
        // tell would be a null finished_at.
        $run = $this->record($workflow, $context, WorkflowRun::STATUS_RUNNING, [
            'started_at' => now(),
        ]);

        $failed = false;

        foreach ($workflow->actions as $action) {
            if (! $action->is_active) {
                WorkflowActionRun::create([
                    'workflow_run_id' => $run->id,
                    'workflow_action_id' => $action->id,
                    'action_type' => $action->action_type,
                    'status' => WorkflowActionRun::STATUS_SKIPPED,
                    'error' => 'Action is switched off.',
                ]);

                continue;
            }

            $actionRun = WorkflowActionRun::create([
                'workflow_run_id' => $run->id,
                'workflow_action_id' => $action->id,
                'action_type' => $action->action_type,
                'status' => WorkflowActionRun::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            try {
                $output = $this->registry
                    ->resolveAction($action->action_type)
                    ->handle($context, $action->config ?? []);

                $actionRun->forceFill([
                    'status' => WorkflowActionRun::STATUS_COMPLETED,
                    'output' => $output,
                    'finished_at' => now(),
                ])->save();
            } catch (Throwable $e) {
                $failed = true;

                $actionRun->forceFill([
                    'status' => WorkflowActionRun::STATUS_FAILED,
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                ])->save();

                // Logged as well as recorded: the run row is for the operator,
                // this is for whoever is reading the application log when a
                // provider starts refusing requests.
                Log::error('Workflow action failed.', [
                    'workflow' => $workflow->slug,
                    'action_type' => $action->action_type,
                    'run_uuid' => $run->uuid,
                    'error' => $e->getMessage(),
                ]);

                if ($action->halt_on_failure) {
                    break;
                }
            }
        }

        $run->forceFill([
            'status' => $failed ? WorkflowRun::STATUS_FAILED : WorkflowRun::STATUS_COMPLETED,
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /**
     * Record that a workflow was withheld because it had already acted on this
     * record in this chain.
     *
     * A `skipped` row rather than a new status, because that is what it is from
     * the operator's side — the workflow did not run — and `skip_reason` says
     * plainly which of the two it was.
     */
    public function suppress(Workflow $workflow, WorkflowContext $context): WorkflowRun
    {
        return $this->record($workflow, $context, WorkflowRun::STATUS_SKIPPED, [
            'skip_reason' => 'Already ran for this record in this chain; withheld to prevent a loop.',
        ]);
    }

    private function record(Workflow $workflow, WorkflowContext $context, string $status, array $extra = []): WorkflowRun
    {
        return WorkflowRun::create(array_merge([
            'workflow_id' => $workflow->id,
            'subject_type' => $context->subject?->getMorphClass(),
            'subject_id' => $context->subject?->getKey(),
            'trigger_type' => $context->triggerType,
            'status' => $status,
            'context' => $context->toLog(),
        ], $extra));
    }

    /**
     * Name the first condition that rejected the run, in words.
     *
     * Re-evaluates one condition at a time. Slightly wasteful, and only on the
     * path that already decided not to do any work — worth it, because
     * "conditions not met" tells an operator nothing they did not already know.
     */
    private function explainSkip(array $conditions, WorkflowContext $context): string
    {
        foreach ($conditions as $condition) {
            if (! is_array($condition) || ! isset($condition['field'])) {
                continue;
            }

            if (VisibleWhen::passes([$condition], $context->accessor())) {
                continue;
            }

            $field = (string) $condition['field'];
            $operator = (string) ($condition['operator'] ?? 'equals');
            $expected = $condition['value'] ?? null;
            $actual = $context->get($field);

            return sprintf(
                '%s %s %s — actual: %s',
                $field,
                str_replace('_', ' ', $operator),
                $this->scalar($expected),
                $this->scalar($actual),
            );
        }

        return 'Conditions not met.';
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value) ?: 'array';
        }

        return '"'.$value.'"';
    }
}
