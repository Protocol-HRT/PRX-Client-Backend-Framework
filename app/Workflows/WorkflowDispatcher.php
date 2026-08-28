<?php

namespace App\Workflows;

use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\Log;

/**
 * Finds the workflows that care about a trigger, and runs them in order.
 *
 * ─── The loop problem, and why this class holds state ───────────────────
 *
 * The most useful action this engine offers is "update a field", and the most
 * useful trigger is "a field changed". Together they are a cycle: a workflow that
 * moves a lead's disposition fires the disposition-changed trigger, which matches
 * the same workflow, which moves it again. That is not an exotic edge case — it
 * is the FIRST thing an operator will build, because "when the quiz completes,
 * move the lead to quiz-complete" and "when a lead reaches quiz-complete, do
 * something" are the two halves of the same funnel.
 *
 * Two guards, because they fail differently:
 *
 *   RE-ENTRY  A given workflow runs at most once per subject per chain. This
 *             catches the direct cycle above, and it is the guard that matters,
 *             because the cycle is otherwise infinite and each turn writes rows.
 *
 *   DEPTH     A hard cap on how deep a chain of workflows triggering workflows
 *             may go. Catches the indirect cycle — A moves a field B watches, B
 *             moves a field A watches — which re-entry alone would not, since
 *             neither workflow repeats for the same subject until the second lap.
 *
 * The chain is per-process and cleared when it drains. A queued listener gets a
 * fresh chain, which is correct: a workflow reacting to a change made by an
 * earlier, already-finished chain is a new causal chain, not a loop.
 */
class WorkflowDispatcher
{
    /**
     * How deep a chain of workflows triggering workflows may go.
     *
     * Five is arbitrary but not meaningless: it is more nesting than any funnel
     * anyone has described wanting, and few enough that a runaway is caught in
     * milliseconds rather than after thousands of rows.
     */
    public const MAX_DEPTH = 5;

    /** @var array<string, true> "workflowId:subjectType:subjectId" already run in this chain. */
    private array $seen = [];

    private int $depth = 0;

    public function __construct(
        private readonly WorkflowRegistry $registry,
        private readonly WorkflowRunner $runner,
    ) {}

    /**
     * @return list<WorkflowRun>
     */
    public function dispatch(string $triggerType, string $triggerTarget, WorkflowContext $context): array
    {
        if ($this->depth >= self::MAX_DEPTH) {
            // Loud, because reaching this means a funnel is misconfigured in a way
            // that would otherwise run forever, and silence would make it look
            // like the workflow simply stopped working.
            Log::warning('Workflow chain exceeded maximum depth; refusing to go deeper.', [
                'trigger_type' => $triggerType,
                'trigger_target' => $triggerTarget,
                'max_depth' => self::MAX_DEPTH,
                'subject' => $context->subject?->getKey(),
            ]);

            return [];
        }

        $workflows = Workflow::forTrigger($triggerType, $triggerTarget)->with('actions')->get();

        if ($workflows->isEmpty()) {
            return [];
        }

        $this->depth++;
        $runs = [];

        try {
            foreach ($workflows as $workflow) {
                $fingerprint = $this->fingerprint($workflow, $context);

                if ($fingerprint !== null && isset($this->seen[$fingerprint])) {
                    // Recorded, never silent. Suppression is now rare enough to be
                    // interesting — it means a workflow that DID act is being asked
                    // to act again on the same record in one chain, which is the
                    // loop — and a run log that goes quiet at exactly that moment
                    // is the log failing at its one job.
                    $runs[] = $this->runner->suppress($workflow, $context);

                    continue;
                }

                // CLAIM BEFORE RUNNING, RELEASE IF IT DID NOTHING.
                //
                // The claim has to happen first: an action's own write fires the
                // next trigger from INSIDE run(), so a mark applied afterwards
                // arrives too late to stop the re-entry it exists to stop.
                //
                // But a workflow that turns out to have SKIPPED performed no
                // actions, created no triggers, and cannot be part of a loop —
                // so it releases the claim. Holding it would suppress that
                // workflow for the rest of the chain in the ordinary two-stage
                // funnel, where an earlier workflow makes a later one's condition
                // TRUE and the later one must then be free to fire. That failure
                // is worse than the loop: the workflow never runs and nothing
                // says why.
                if ($fingerprint !== null) {
                    $this->seen[$fingerprint] = true;
                }

                $run = $this->runner->run($workflow, $context);
                $runs[] = $run;

                if ($fingerprint !== null && $run->status === WorkflowRun::STATUS_SKIPPED) {
                    unset($this->seen[$fingerprint]);
                }

                // Only a MATCH stops the rest. A workflow that was evaluated and
                // skipped has not claimed the trigger, so "stop on first match"
                // means what it says rather than "stop on first workflow".
                if ($workflow->stop_on_first_match && $run->status !== WorkflowRun::STATUS_SKIPPED) {
                    break;
                }
            }
        } finally {
            $this->depth--;

            // The chain has drained; the next trigger is a new causal chain and
            // must not inherit this one's re-entry bookkeeping.
            if ($this->depth === 0) {
                $this->seen = [];
            }
        }

        return $runs;
    }

    /** Convenience for model triggers, which are the common case. */
    public function dispatchForModel(string $triggerType, WorkflowContext $context): array
    {
        if ($context->subjectKey === null) {
            return [];
        }

        return $this->dispatch($triggerType, $context->subjectKey, $context);
    }

    /**
     * Dispatch every workflow registered against a domain event class.
     *
     * One event class may carry several registry keys — an install can register
     * the same event under two names with different subjects — so this fans out
     * rather than assuming one.
     */
    public function dispatchForEvent(string $eventClass, WorkflowContext $context): array
    {
        $runs = [];

        foreach ($this->registry->keysForEvent($eventClass) as $key) {
            $definition = $this->registry->event($key);

            $scoped = new WorkflowContext(
                triggerType: 'event_fired',
                triggerTarget: $key,
                subject: $context->subject,
                subjectKey: $definition['subject'] ?? $context->subjectKey,
                original: $context->original,
                changed: $context->changed,
                payload: $context->payload,
            );

            $runs = array_merge($runs, $this->dispatch('event_fired', $key, $scoped));
        }

        return $runs;
    }

    private function fingerprint(Workflow $workflow, WorkflowContext $context): ?string
    {
        if ($context->subject === null) {
            return null;
        }

        return $workflow->id.':'.$context->subject->getMorphClass().':'.$context->subject->getKey();
    }
}
