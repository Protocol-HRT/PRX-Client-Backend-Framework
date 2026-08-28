<?php

namespace App\Workflows\Contracts;

use App\Workflows\WorkflowContext;

/**
 * One thing a workflow can do.
 *
 * A handler receives the operator's config blob and the context of the run. It
 * returns whatever is worth putting in the log — a webhook's status code, a
 * provider's message id — which is how `workflow_action_runs.output` stays
 * useful without a column per action type.
 *
 * THROW TO FAIL. The runner catches, records the message against this step, and
 * decides whether to continue based on the action's `halt_on_failure`. A handler
 * should not swallow its own errors: a marketing push that silently does nothing
 * is indistinguishable from one that worked, which is the state operators cannot
 * debug.
 */
interface WorkflowActionHandler
{
    /**
     * @param  array<string, mixed>  $config  The operator's configuration for this step.
     * @return array<string, mixed> Anything worth recording in the run log.
     */
    public function handle(WorkflowContext $context, array $config): array;
}
