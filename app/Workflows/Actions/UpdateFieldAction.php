<?php

namespace App\Workflows\Actions;

use App\Workflows\Contracts\WorkflowActionHandler;
use App\Workflows\WorkflowContext;
use App\Workflows\WorkflowRegistry;
use RuntimeException;

/**
 * Set a field on the subject.
 *
 * The action that makes the funnel a funnel: "when the quiz completes, move this
 * lead to quiz-complete" — and because a disposition change is itself a trigger,
 * that movement is what the next workflow reacts to. The operator composes stages
 * without anyone writing code for the transitions between them.
 *
 * BOUNDED BY THE SUBJECT'S REGISTERED FIELD LIST. Config rows are operator-
 * editable, so without the allow-list this action would be "write any column on
 * any watched model", which in a product other companies deploy is a way to set
 * someone's `email` or a price. An unregistered field throws rather than being
 * quietly ignored — a workflow that silently does nothing is the hardest kind to
 * debug.
 *
 * Config: {"field": "status", "value": "quiz_complete"}
 */
class UpdateFieldAction implements WorkflowActionHandler
{
    public function __construct(private readonly WorkflowRegistry $registry) {}

    public function handle(WorkflowContext $context, array $config): array
    {
        $field = $config['field'] ?? null;

        if (! is_string($field) || $field === '') {
            throw new RuntimeException('No field configured for the update-field action.');
        }

        if ($context->subject === null || $context->subjectKey === null) {
            throw new RuntimeException('The update-field action needs a subject, and this trigger carries none.');
        }

        if (! $this->registry->allowsField($context->subjectKey, $field)) {
            throw new RuntimeException(
                "Field [{$field}] is not writable on [{$context->subjectKey}]. Register it on the subject to allow it."
            );
        }

        $value = $config['value'] ?? null;
        $before = $context->subject->getAttribute($field);

        if ($before === $value) {
            // Not an error, and worth recording as a no-op: it is the normal
            // result of a workflow that re-runs, and the re-entry guard depends on
            // this being cheap rather than on it never happening.
            return ['field' => $field, 'changed' => false, 'value' => $value];
        }

        $context->subject->forceFill([$field => $value])->save();

        return ['field' => $field, 'changed' => true, 'from' => $before, 'to' => $value];
    }
}
