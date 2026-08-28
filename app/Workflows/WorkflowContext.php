<?php

namespace App\Workflows;

use Illuminate\Database\Eloquent\Model;

/**
 * Everything one workflow evaluation knows about what just happened.
 *
 * THE INTERESTING PART IS `get()`, and specifically the reserved prefixes. A
 * workflow's most useful conditions are about CHANGE — "became quiz_complete",
 * "moved *from* new" — and a plain attribute read cannot express that, because by
 * the time anything evaluates, the old value is gone.
 *
 * Rather than fork VisibleWhen to teach it about changes (the project rule is to
 * reuse it, never fork it), the change information is exposed as pseudo-fields in
 * the same flat namespace VisibleWhen already reads:
 *
 *   status              the value now
 *   _original.status    the value before this write
 *   _changed.status     '1' when this write altered it, '' otherwise
 *
 * So "moved to quiz_complete from new" is two ordinary conditions, and the
 * condition builder needs no new operators:
 *
 *   [{field: 'status',           operator: 'equals', value: 'quiz_complete'},
 *    {field: '_original.status', operator: 'equals', value: 'new'}]
 *
 * `_changed.*` returns a string rather than a bool because VisibleWhen compares
 * via string casts; '1' and '' are what a truthy and falsy comparison need to
 * look like there.
 */
class WorkflowContext
{
    /**
     * @param  array<string, mixed>  $original  Attributes as they were before the write.
     * @param  list<string>  $changed  Attribute names this write altered.
     * @param  array<string, mixed>  $payload  Extra data from an event trigger.
     */
    public function __construct(
        public readonly string $triggerType,
        public readonly string $triggerTarget,
        public readonly ?Model $subject = null,
        public readonly ?string $subjectKey = null,
        public readonly array $original = [],
        public readonly array $changed = [],
        public readonly array $payload = [],
    ) {}

    /**
     * Read a field for condition evaluation.
     *
     * Reads are bounded by the subject's registered field list — an attribute the
     * install did not register is not readable, so a condition cannot be pointed
     * at a column the operator was never meant to see. Unregistered subjects
     * allow nothing, which is why this fails closed rather than falling back to
     * the raw model.
     */
    public function get(string $field): mixed
    {
        if (str_starts_with($field, '_original.')) {
            $attribute = substr($field, 10);

            return $this->allows($attribute) ? ($this->original[$attribute] ?? null) : null;
        }

        if (str_starts_with($field, '_changed.')) {
            $attribute = substr($field, 9);

            // Bounded like every other read. Without this an operator could
            // condition on `_changed.password` — no value leaks, but a boolean
            // about an unregistered column is still information the allow-list
            // exists to withhold.
            if (! $this->allows($attribute)) {
                return '';
            }

            return in_array($attribute, $this->changed, true) ? '1' : '';
        }

        if (str_starts_with($field, '_payload.')) {
            return data_get($this->payload, substr($field, 9));
        }

        if (! $this->allows($field)) {
            return null;
        }

        return $this->subject?->getAttribute($field);
    }

    /** A callable for VisibleWhen::passes(). */
    public function accessor(): callable
    {
        return fn (string $field): mixed => $this->get($field);
    }

    private function allows(string $field): bool
    {
        if ($this->subjectKey === null) {
            return false;
        }

        return app(WorkflowRegistry::class)->allowsField($this->subjectKey, $field);
    }

    /**
     * A snapshot for the run log.
     *
     * Records only the registered fields, plus what changed — enough to explain
     * afterwards why a workflow did or did not match, without copying the whole
     * row (and with it, every field the operator was not allowed to condition on)
     * into a log table.
     */
    public function toLog(): array
    {
        $fields = $this->subjectKey === null
            ? []
            : array_keys(app(WorkflowRegistry::class)->fieldsFor($this->subjectKey));

        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field] = $this->subject?->getAttribute($field);
        }

        return [
            'trigger_type' => $this->triggerType,
            'trigger_target' => $this->triggerTarget,
            'attributes' => $attributes,
            // Filtered too. This payload goes out over webhooks, so an
            // unregistered column must not appear even as a NAME in a list of
            // what changed.
            'changed' => array_values(array_intersect($this->changed, $fields)),
            'original' => array_intersect_key($this->original, array_flip($fields)),
            // Event payload is scalars the event itself chose to expose. It is
            // NOT bounded by the subject allow-list — it is not subject data —
            // so an event carrying something sensitive should not make it public.
            'payload' => $this->payload,
        ];
    }
}
