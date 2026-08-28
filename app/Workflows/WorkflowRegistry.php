<?php

namespace App\Workflows;

use App\Enums\Integrations\IntegrationCapability;
use App\Enums\Privacy\DataClassification;
use App\Integrations\IntegrationRegistry;
use App\Workflows\Contracts\WorkflowActionHandler;
use Closure;
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
    /** @var array<string, array{model: class-string<Model>, label: string, fields: array<string, array{label: string, class: DataClassification}>}> */
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
     * @param  array<string, string|array{label: string, class?: DataClassification}>  $fields
     *                                                                                          attribute => human label, or attribute => ['label' => …, 'class' => …].
     *
     *   THIS IS AN ALLOW-LIST, NOT DOCUMENTATION. It bounds what conditions may
     *   read, what an update-field action may write, and what a field mapper may
     *   send to a third party — so a workflow cannot be pointed at `password` or
     *   a hidden column.
     *
     *   The optional `class` says how sensitive the field is, which is what the
     *   PHI gate compares against a destination's permissions. It hangs HERE
     *   rather than on the model because this list is already the boundary
     *   everything outbound passes through; classifying anywhere else would mean
     *   a field could reach a destination without its classification travelling
     *   with it. A bare string label means `general`, so every existing
     *   registration keeps working unchanged.
     */
    public function registerSubject(string $key, string $model, string $label, array $fields = []): void
    {
        $this->subjects[$key] = [
            'model' => $model,
            'label' => $label,
            'fields' => array_map(
                fn (string|array $field): array => is_array($field)
                    ? ['label' => $field['label'], 'class' => $field['class'] ?? DataClassification::General]
                    : ['label' => $field, 'class' => DataClassification::General],
                $fields,
            ),
        ];
    }

    /** @return array<string, array<string, mixed>> */
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
        return array_map(
            fn (array $field): string => $field['label'],
            $this->subjects[$subjectKey]['fields'] ?? [],
        );
    }

    /**
     * How sensitive one of a subject's fields is.
     *
     * FAILS CLOSED, like every other read bounded by this allow-list: an
     * unregistered field is treated as health data rather than as general data.
     * The asymmetry is deliberate — guessing "general" for something nobody
     * classified would let an unknown field through the PHI gate silently, and a
     * field nobody has thought about is precisely the one to be careful with.
     */
    public function classificationFor(string $subjectKey, string $field): DataClassification
    {
        return $this->subjects[$subjectKey]['fields'][$field]['class'] ?? DataClassification::Phi;
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

    // ─── Actions: what a workflow can do ─────────────────────────────

    /**
     * @param  string  $type  Stored in `workflow_actions.action_type`.
     * @param  class-string<WorkflowActionHandler>  $handler
     * @param  IntegrationCapability|null  $capability  Offer this action only while some enabled
     *                                                  integration provides this capability. Null =
     *                                                  always offered.
     * @param  Closure|null  $configSchema  Returns Filament components for this action's config.
     *                                      Null falls back to the generic key/value editor, so the
     *                                      actions that predate per-type forms keep working.
     */
    public function registerAction(
        string $type,
        string $handler,
        string $label,
        ?string $description = null,
        ?IntegrationCapability $capability = null,
        ?Closure $configSchema = null,
    ): void {
        $this->actions[$type] = [
            'handler' => $handler,
            'label' => $label,
            'description' => $description,
            'capability' => $capability,
            'config_schema' => $configSchema,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * The action types an operator may currently choose.
     *
     * FILTERED BY WHAT IS ACTUALLY CONFIGURED. "Send an email" is not offered
     * while no integration provides transactional email, because an action that
     * can only fail is worse than an absent one — the operator builds the funnel,
     * sees no error, and discovers weeks later that the step never worked.
     * Enabling an integration is what makes its actions appear.
     *
     * This filters the FORM only. `resolveAction()` deliberately does not filter,
     * so a workflow authored while an integration was enabled keeps failing
     * loudly per run after it is switched off, rather than quietly becoming a
     * no-op that nothing explains.
     *
     * @return array<string, string> type => label, for a Filament select.
     */
    public function actionOptions(): array
    {
        return collect($this->actions)
            ->filter(fn (array $definition): bool => $this->capabilityIsAvailable($definition['capability'] ?? null))
            ->map(fn (array $definition): string => $definition['label'])
            ->all();
    }

    /** The capability an action needs, if any. */
    public function capabilityFor(string $type): ?IntegrationCapability
    {
        return $this->actions[$type]['capability'] ?? null;
    }

    /**
     * Filament components for one action's config, or null for the generic editor.
     *
     * @return list<mixed>|null
     */
    public function configSchemaFor(?string $type): ?array
    {
        $schema = $type === null ? null : ($this->actions[$type]['config_schema'] ?? null);

        return $schema instanceof Closure ? $schema() : null;
    }

    private function capabilityIsAvailable(?IntegrationCapability $capability): bool
    {
        if ($capability === null) {
            return true;
        }

        return app(IntegrationRegistry::class)->instancesOffering($capability)->isNotEmpty();
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
