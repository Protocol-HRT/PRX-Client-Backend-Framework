<?php

namespace App\Workflows;

use App\Workflows\Contracts\WorkflowActionHandler;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * What this install allows workflows to watch, and to do.
 *
 * THE REASON THIS EXISTS RATHER THAN AN ENUM. This backend is deployed by people
 * we will never meet, running funnels we cannot anticipate. An enum of trigger
 * models or action types would mean every one of them forks the codebase to add
 * their own. A registry means they call `registerSubject()` / `registerAction()`
 * from their own service provider and the admin UI grows to match.
 *
 * IT IS ALSO THE SECURITY BOUNDARY, and that is not a secondary benefit. Rows in
 * `workflows` and `workflow_actions` are operator-editable and name what to run.
 * If those rows held class names that got instantiated, anyone who reached the
 * admin — or any bug that let a row be written — would have arbitrary class
 * instantiation in a product hundreds of companies deploy. They hold REGISTRY
 * KEYS instead, which resolve only to something this install deliberately
 * registered, and resolve to nothing otherwise.
 *
 * Registered in WorkflowServiceProvider. Bound as a singleton, so a package or a
 * client's own provider can add to it at boot.
 */
class WorkflowRegistry
{
    /** @var array<string, array{model: class-string<Model>, label: string, fields: array<string, string>}> */
    private array $subjects = [];

    /** @var array<string, array{event: class-string, label: string, subject: string|null, changed_field: string|null, subject_property: string|null}> */
    private array $events = [];

    /** @var array<string, array{handler: class-string<WorkflowActionHandler>, label: string, description: string|null}> */
    private array $actions = [];

    /** @var array<string, array{job: class-string, label: string}> */
    private array $jobs = [];

    // ─── Subjects: models a workflow can trigger on ──────────────────

    /**
     * @param  string  $key  Stable key stored in `workflows.trigger_target`.
     * @param  class-string<Model>  $model
     * @param  array<string, string>  $fields  attribute => human label. This is
     *                                         an ALLOW-LIST, not documentation: it bounds what conditions may
     *                                         read and what an update-field action may write, so a workflow
     *                                         cannot be pointed at `password` or a hidden column.
     */
    public function registerSubject(string $key, string $model, string $label, array $fields = []): void
    {
        $this->subjects[$key] = ['model' => $model, 'label' => $label, 'fields' => $fields];
    }

    /** @return array<string, array{model: class-string<Model>, label: string, fields: array<string, string>}> */
    public function subjects(): array
    {
        return $this->subjects;
    }

    public function subject(string $key): ?array
    {
        return $this->subjects[$key] ?? null;
    }

    /** The registry key for a model instance, or null if it is not watched at all. */
    public function keyForModel(Model $model): ?string
    {
        foreach ($this->subjects as $key => $definition) {
            if ($model instanceof $definition['model']) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, string> attribute => label, for a condition builder. */
    public function fieldsFor(string $subjectKey): array
    {
        return $this->subjects[$subjectKey]['fields'] ?? [];
    }

    /**
     * Whether a field may be read or written for this subject.
     *
     * An UNREGISTERED subject allows nothing. Failing closed matters here: a
     * typo'd subject key must not silently become "anything goes".
     */
    public function allowsField(string $subjectKey, string $field): bool
    {
        return array_key_exists($field, $this->fieldsFor($subjectKey));
    }

    // ─── Events: domain events a workflow can trigger on ─────────────

    /**
     * @param  string  $key  Stored in `workflows.trigger_target`, e.g. 'lead.created'.
     * @param  string|null  $subject  Registry key of the model the event carries,
     *                                so conditions can read its fields.
     * @param  string|null  $changedField  Which SUBJECT FIELD this event's `from`/`to`
     *                                     properties describe. Declared rather than assumed: an event named
     *                                     `PriceChanged { Product $product; float $from; float $to; }` describes a
     *                                     price, and a generic layer that guessed "status" would make
     *                                     `_changed.status` match on a price move — wrongly, invisibly, and three
     *                                     layers from wherever the adopter registered it. Null means the event
     *                                     reports no field change.
     * @param  string|null  $subjectProperty  Which property holds the subject, for an
     *                                        event carrying more than one model. Null resolves by type against the
     *                                        registered subject, then by first model found.
     */
    public function registerEvent(
        string $key,
        string $event,
        string $label,
        ?string $subject = null,
        ?string $changedField = null,
        ?string $subjectProperty = null,
    ): void {
        $this->events[$key] = [
            'event' => $event,
            'label' => $label,
            'subject' => $subject,
            'changed_field' => $changedField,
            'subject_property' => $subjectProperty,
        ];
    }

    /** @return array<string, array{event: class-string, label: string, subject: string|null}> */
    public function events(): array
    {
        return $this->events;
    }

    public function event(string $key): ?array
    {
        return $this->events[$key] ?? null;
    }

    /** @return list<string> every registry key registered for this event class. */
    public function keysForEvent(string $eventClass): array
    {
        return array_keys(array_filter(
            $this->events,
            fn (array $d): bool => $d['event'] === $eventClass,
        ));
    }

    // ─── Actions: what a workflow can do ─────────────────────────────

    /**
     * @param  string  $type  Stored in `workflow_actions.action_type`.
     * @param  class-string<WorkflowActionHandler>  $handler
     */
    public function registerAction(string $type, string $handler, string $label, ?string $description = null): void
    {
        $this->actions[$type] = ['handler' => $handler, 'label' => $label, 'description' => $description];
    }

    /** @return array<string, array{handler: class-string<WorkflowActionHandler>, label: string, description: string|null}> */
    public function actions(): array
    {
        return $this->actions;
    }

    /** @return array<string, string> type => label, for a Filament select. */
    public function actionOptions(): array
    {
        return array_map(fn (array $d): string => $d['label'], $this->actions);
    }

    /**
     * Resolve an action type to its handler.
     *
     * Throws rather than returning null: an unregistered type means the workflow
     * references something this install does not have — a package removed, a key
     * mistyped — and running the rest of the workflow as though nothing were
     * missing would be the wrong kind of resilient. The runner catches it and
     * records a failed action, so the operator sees which step is broken.
     */
    public function resolveAction(string $type): WorkflowActionHandler
    {
        if (! isset($this->actions[$type])) {
            throw new InvalidArgumentException("No workflow action registered for type [{$type}].");
        }

        return app($this->actions[$type]['handler']);
    }

    public function hasAction(string $type): bool
    {
        return isset($this->actions[$type]);
    }

    // ─── Jobs: the allow-list for the dispatch-job action ────────────

    /**
     * Register a job a workflow may dispatch.
     *
     * SEPARATE FROM ACTIONS AND DELIBERATELY NARROW. "Dispatch a job" is the most
     * dangerous thing this engine can offer, because the job name would otherwise
     * come from an operator-editable row. Only jobs registered here can be named,
     * so the blast radius is whatever this install chose to expose.
     *
     * CONTRACT: a registered job's constructor must accept a single
     * `?Illuminate\Database\Eloquent\Model` — the record that triggered the
     * workflow, which is null for a trigger with no subject. A mismatched
     * signature fails per run and is recorded against the step.
     */
    public function registerJob(string $key, string $job, string $label): void
    {
        $this->jobs[$key] = ['job' => $job, 'label' => $label];
    }

    /** @return array<string, string> key => label. */
    public function jobOptions(): array
    {
        return array_map(fn (array $d): string => $d['label'], $this->jobs);
    }

    public function resolveJob(string $key): ?string
    {
        return $this->jobs[$key]['job'] ?? null;
    }
}
