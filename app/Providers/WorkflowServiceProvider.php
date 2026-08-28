<?php

namespace App\Providers;

use App\Events\Leads\LeadCreated;
use App\Events\Leads\LeadDispositionChanged;
use App\Events\Quiz\QuizCompleted;
use App\Models\Lead;
use App\Workflows\Actions\DispatchJobAction;
use App\Workflows\Actions\UpdateFieldAction;
use App\Workflows\Actions\WebhookAction;
use App\Workflows\WorkflowContext;
use App\Workflows\WorkflowDispatcher;
use App\Workflows\WorkflowRegistry;
use App\Workflows\WorkflowSubjectObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the workflow engine, and declares what THIS install exposes to it.
 *
 * THE SPLIT IS THE POINT. Everything in App\Workflows is generic and knows
 * nothing about leads, quizzes or any particular business. This file is the only
 * place a domain concept meets the engine — so another company deploying this
 * backend replaces the contents of `registerAtlasSubjects()` and
 * `registerAtlasEvents()` (or adds their own provider) and the engine, the admin
 * UI and the condition builder all follow, with no fork.
 *
 * The field lists below are ALLOW-LISTS, not documentation. They bound what a
 * condition may read and what an update-field action may write; anything absent
 * is invisible and unwritable. Adding a field here is a deliberate act.
 */
class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singletons: the registry so packages can add to it at boot, the
        // dispatcher because its loop guard is per-chain state and a fresh
        // instance per resolve would defeat it entirely.
        $this->app->singleton(WorkflowRegistry::class);
        $this->app->singleton(WorkflowDispatcher::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(WorkflowRegistry::class);

        $this->registerActions($registry);
        $this->registerAtlasSubjects($registry);
        $this->registerAtlasEvents($registry);

        $this->attachObservers($registry);
        $this->bridgeEvents($registry);
    }

    /**
     * Generic actions. Deliberately NOT 'push to Klaviyo' or 'send to GHL' —
     * those arrive as configured integration instances behind one
     * `push_to_integration` action, so the shipped enum never names a vendor.
     */
    private function registerActions(WorkflowRegistry $registry): void
    {
        $registry->registerAction(
            'update_field',
            UpdateFieldAction::class,
            'Update a field',
            'Set a field on the record that triggered this workflow. Moving a lead between dispositions is itself a trigger, so this is how stages chain together.',
        );

        $registry->registerAction(
            'webhook',
            WebhookAction::class,
            'Send a webhook',
            'POST the record to any URL. The escape hatch for a system with no first-class integration yet.',
        );

        $registry->registerAction(
            'dispatch_job',
            DispatchJobAction::class,
            'Run a background job',
            'Dispatch one of this installation\'s registered jobs.',
        );
    }

    private function registerAtlasSubjects(WorkflowRegistry $registry): void
    {
        $registry->registerSubject('lead', Lead::class, 'Lead', [
            'status' => 'Disposition',
            'email' => 'Email',
            'phone' => 'Phone',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'age' => 'Age',
            'gender' => 'Sex',
            'country' => 'Country',
            'state' => 'State',
            'checkout_path' => 'Checkout path',
            'email_consent' => 'Email consent',
            'sms_consent' => 'SMS consent',
            'utm_source' => 'UTM source',
            'utm_medium' => 'UTM medium',
            'utm_campaign' => 'UTM campaign',
            'cart_subtotal' => 'Cart subtotal',
            'quiz_id' => 'Quiz',
        ]);
    }

    private function registerAtlasEvents(WorkflowRegistry $registry): void
    {
        $registry->registerEvent('lead.created', LeadCreated::class, 'Lead captured (any source)', 'lead');
        $registry->registerEvent('lead.disposition_changed', LeadDispositionChanged::class, 'Lead moved disposition', 'lead', changedField: 'status');
        $registry->registerEvent('quiz.completed', QuizCompleted::class, 'Quiz completed', 'lead');
    }

    private function attachObservers(WorkflowRegistry $registry): void
    {
        foreach ($registry->subjects() as $definition) {
            $definition['model']::observe(WorkflowSubjectObserver::class);
        }
    }

    /**
     * Bridge domain events into the engine.
     *
     * Every registered event carries its subject on a `$lead`-style property; the
     * bridge finds the first model property rather than demanding every event in
     * the product adopt one interface. An event with no model still fires — its
     * conditions simply have nothing to read, which is correct for a trigger that
     * genuinely has no subject.
     */
    private function bridgeEvents(WorkflowRegistry $registry): void
    {
        foreach ($registry->events() as $key => $definition) {
            Event::listen($definition['event'], function (object $event) use ($key, $definition, $registry): void {
                $subject = $this->subjectOf($event, $definition, $registry);

                // WHICH FIELD `from`/`to` DESCRIBE IS DECLARED, NEVER GUESSED.
                // Assuming 'status' would make `_changed.status` fire on an event
                // about a price, in an install this codebase has never seen.
                $changedField = $definition['changed_field'] ?? null;

                $original = [];
                $changed = [];

                if ($changedField !== null && property_exists($event, 'from')) {
                    $original[$changedField] = $event->from;
                }

                if ($changedField !== null && property_exists($event, 'to')) {
                    $changed[] = $changedField;
                }

                $this->app->make(WorkflowDispatcher::class)->queue('event_fired', $key, new WorkflowContext(
                    triggerType: 'event_fired',
                    triggerTarget: $key,
                    subject: $subject,
                    subjectKey: $definition['subject'],
                    original: $original,
                    changed: $changed,
                    payload: $this->payloadOf($event),
                ));
            });
        }
    }

    /**
     * Find the model this event is about.
     *
     * Resolution order, narrowest first: the declared property, then the first
     * property whose TYPE matches the registered subject's model, and only then
     * the first model of any kind. The middle step is what stops an event
     * carrying two models — an order and a customer — from binding conditions to
     * whichever happened to be declared first, which would read null for every
     * allow-listed field and skip silently.
     */
    private function subjectOf(object $event, array $definition, WorkflowRegistry $registry): ?Model
    {
        $vars = get_object_vars($event);

        $property = $definition['subject_property'] ?? null;

        if ($property !== null && ($vars[$property] ?? null) instanceof Model) {
            return $vars[$property];
        }

        $expected = $definition['subject'] === null
            ? null
            : ($registry->subject($definition['subject'])['model'] ?? null);

        if ($expected !== null) {
            foreach ($vars as $value) {
                if ($value instanceof $expected) {
                    return $value;
                }
            }
        }

        foreach ($vars as $value) {
            if ($value instanceof Model) {
                return $value;
            }
        }

        return null;
    }

    /** Scalar properties of the event, readable in conditions as `_payload.*`. */
    private function payloadOf(object $event): array
    {
        return array_filter(
            get_object_vars($event),
            fn ($value): bool => is_scalar($value) || $value === null,
        );
    }
}
