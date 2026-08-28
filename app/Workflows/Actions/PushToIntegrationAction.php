<?php

namespace App\Workflows\Actions;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\ConsentResolver;
use App\Integrations\Contracts\EnrollsInAutomations;
use App\Integrations\Contracts\SyncsContacts;
use App\Integrations\Contracts\TracksEvents;
use App\Integrations\FieldMap;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Messages\ContactPayload;
use App\Models\Integrations\IntegrationIdentity;
use App\Models\Integrations\IntegrationInstance;
use App\Workflows\Contracts\WorkflowActionHandler;
use App\Workflows\WorkflowContext;
use RuntimeException;
use Throwable;

/**
 * Send the record to a configured integration.
 *
 * ─── ONE ACTION, NOT ONE PER VENDOR ────────────────────────────────────
 *
 * There is no `push_to_klaviyo`. A vendor case in the shipped action registry
 * would mean every install using a different CRM has to fork the product to add
 * theirs — and this backend ships to companies whose CRMs we have not met. So
 * the action is generic and its config names a configured INSTANCE:
 *
 *   {"integration": "klaviyo-marketing", "operation": "sync_contact",
 *    "group": "quiz-completers",
 *    "mappings": [{"source": "email", "destination": "email"},
 *                 {"source": "quiz_answers.goals", "destination": "goals",
 *                  "on_phi": "redact"}]}
 *
 * The vendor is named where a vendor belongs: in the driver registry, which is
 * code, and in the instance row, which is the operator's.
 *
 * ─── Operations are capability-checked, not assumed ────────────────────
 *
 * Klaviyo and GoHighLevel are mechanically opposite: Klaviyo has an events API
 * and forbids pushing somebody straight into an automation, GoHighLevel has no
 * events API and allows exactly that. So an operation is offered only when the
 * chosen instance's driver implements the interface behind it, and asking for
 * one it does not implement fails with a sentence saying so rather than doing
 * nothing.
 *
 * ─── Nothing leaves without passing the field map ──────────────────────
 *
 * Every value sent is resolved by `FieldMap`, which compares each field's
 * classification against this instance's PHI attestation and blocks, redacts or
 * permits accordingly. Bypassing it — reading the subject directly here — would
 * put the one check that prevents a health-data leak behind a `if` somebody can
 * forget to write.
 *
 * ─── Consent is the exception, and it goes the OTHER way ───────────────
 *
 * `consent` is the one value that deliberately does not come from the mapper.
 * Everything the mapper carries is a field an operator CHOSE to send; consent is
 * an invariant, and a mapping somebody can point anywhere — or forget — is the
 * wrong shape for the thing that decides whether a person may be marketed to. So
 * it is resolved here from our own `lead_consents` audit, travels on the payload
 * out of reach of any mapping, and picks the destination's verb: subscribe where
 * consent exists, plain list-add or nothing where it does not.
 */
class PushToIntegrationAction implements WorkflowActionHandler
{
    public const OP_SYNC_CONTACT = 'sync_contact';

    public const OP_TRACK_EVENT = 'track_event';

    public const OP_ENROLL = 'enroll';

    /** Default: a person with no marketing consent does not go on the list. */
    public const NOT_CONSENTED_SKIP = 'skip';

    /** Add them anyway — for a group that is internal bookkeeping, not an audience. */
    public const NOT_CONSENTED_ADD = 'add';

    public function __construct(
        private readonly IntegrationRegistry $integrations,
        private readonly FieldMap $fields,
        private readonly ConsentResolver $consents,
    ) {}

    public function handle(WorkflowContext $context, array $config): array
    {
        $instance = $this->instance($config);
        $driver = $this->integrations->driverFor($instance);

        if (! $this->integrations->instanceOffers($instance, IntegrationCapability::Crm)) {
            // Reached when an operator switched the capability off after the
            // workflow was built. Loud rather than skipped: a CRM push that
            // silently stops is indistinguishable from one that is working.
            throw new RuntimeException(
                "Integration [{$instance->name}] is no longer enabled for contact sync."
            );
        }

        // THE PHI GATE, AND THE ONLY WAY ANYTHING LEAVES.
        //
        // The identity fields go through it too, via an implicit mapping rather
        // than a direct read. A contact needs something to be identified by, so
        // it is tempting to fall back to reading `email` off the subject when
        // the operator did not map it — and that would be a hole by
        // construction: it happens to be harmless only because this install
        // classifies email as personal rather than health data, and it would
        // start leaking the day somebody reclassified a field the fallback
        // touches.
        //
        // The one value that does not pass through it is `externalId` below —
        // this record's own primary key, which is ours rather than the person's
        // and discloses nothing about them.
        $attributes = $this->fields->apply(
            $this->withIdentity($config['mappings'] ?? []),
            $context,
            $instance,
        );

        $operation = $config['operation'] ?? self::OP_SYNC_CONTACT;

        $contact = new ContactPayload(
            email: $this->stringOrNull($attributes['email'] ?? null),
            phone: $this->stringOrNull($attributes['phone'] ?? null),
            firstName: $this->stringOrNull($attributes['first_name'] ?? null),
            lastName: $this->stringOrNull($attributes['last_name'] ?? null),
            externalId: $this->stringOrNull($context->subject?->getKey()),
            attributes: $attributes,
            // NOT from `$attributes`, and not from the context snapshot either.
            // Consent is an invariant rather than something an operator maps,
            // and it is read live because a chain can run after a withdrawal —
            // the same rule the PHI attestation follows, for the same reason.
            consent: $this->consents->resolve($context->subject),
        );

        return match ($operation) {
            self::OP_SYNC_CONTACT => $this->syncContact($driver, $instance, $contact, $config, $context),
            self::OP_TRACK_EVENT => $this->trackEvent($driver, $instance, $contact, $config, $attributes),
            self::OP_ENROLL => $this->enroll($driver, $instance, $contact, $config, $context),
            default => throw new RuntimeException("Unknown integration operation [{$operation}]."),
        };
    }

    private function syncContact(
        object $driver,
        IntegrationInstance $instance,
        ContactPayload $contact,
        array $config,
        WorkflowContext $context,
    ): array {
        $this->require($driver, SyncsContacts::class, $instance, 'sync contacts');

        $remoteId = $driver->upsertContact($instance, $contact);
        $this->rememberIdentity($instance, $context, $remoteId);

        $group = $config['group'] ?? null;
        $skipped = null;

        if (is_string($group) && $group !== '') {
            // WHETHER A NON-CONSENTED PERSON GOES ON THE LIST AT ALL is the
            // operator's decision, and it defaults to "no". A list is usually an
            // opt-in audience: putting somebody on it who never agreed is the
            // error that reaches a regulator rather than a support inbox, and
            // an abandon funnel is better served by an event than by a list.
            if ($this->skipsGroup($config, $contact)) {
                // Named in the run result rather than passed over in silence.
                // A step that quietly does three-quarters of its job is the
                // failure this whole slice exists to remove.
                $skipped = 'no marketing consent on record';
            } else {
                $driver->addToGroup($instance, $remoteId, $group, $contact);
            }
        }

        // The run log is an unencrypted table any admin with run access can read,
        // and this payload is what a webhook would carry. So it records the
        // REMOTE ID and nothing that was mapped — the values are the operator's
        // data, not a debugging aid. The consented CHANNELS are recorded because
        // "why was this person not subscribed" is otherwise unanswerable, and a
        // channel name discloses nothing the list name does not.
        return array_filter([
            'remote_id' => $remoteId,
            'group' => $group,
            'group_skipped' => $skipped,
            'consented' => $contact->consent?->granted ?: null,
            'fields_sent' => count($contact->attributes),
        ], fn ($value): bool => $value !== null);
    }

    /**
     * Whether the group step is skipped for want of consent.
     *
     * `add` is offered because not every group is a marketing audience — a CRM
     * tag such as "quiz-abandoned" is internal bookkeeping, and refusing to
     * apply it would break a legitimate funnel. It is not the default, and the
     * form says what it means.
     *
     * TESTED AGAINST `add`, NOT FOR `skip`, so an unrecognised value skips.
     * `config` is a JSON column the form is not the only author of — fill
     * scripts write it too — and `"Skip"` or a typo reading as "add them anyway"
     * would opt somebody in through a spelling mistake.
     */
    private function skipsGroup(array $config, ContactPayload $contact): bool
    {
        $behaviour = $config['when_not_consented'] ?? self::NOT_CONSENTED_SKIP;

        return $behaviour !== self::NOT_CONSENTED_ADD
            && ! ($contact->consent?->grantsAnything() ?? false);
    }

    /**
     * Remember what this destination calls the record we just pushed.
     *
     * Best-effort by design: the push already SUCCEEDED at the far end when this
     * runs, so failing the action here would report a failure that did not
     * happen and — with no retry on the queue — leave the operator chasing a
     * profile that exists. A missing identity row costs a redundant upsert next
     * time; a false failure costs trust in the run log.
     */
    private function rememberIdentity(IntegrationInstance $instance, WorkflowContext $context, string $remoteId): void
    {
        $subject = $context->subject;

        if ($subject === null || $subject->getKey() === null) {
            return;
        }

        try {
            IntegrationIdentity::record($instance, $subject, $remoteId);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function trackEvent(object $driver, IntegrationInstance $instance, ContactPayload $contact, array $config, array $attributes): array
    {
        $this->require($driver, TracksEvents::class, $instance, 'record events');

        $event = $config['event'] ?? null;

        if (! is_string($event) || $event === '') {
            throw new RuntimeException('No event name configured for this integration step.');
        }

        $driver->trackEvent($instance, $contact, $event, $attributes);

        return ['event' => $event, 'fields_sent' => count($attributes)];
    }

    private function enroll(
        object $driver,
        IntegrationInstance $instance,
        ContactPayload $contact,
        array $config,
        WorkflowContext $context,
    ): array {
        $this->require($driver, EnrollsInAutomations::class, $instance, 'enrol contacts in automations');

        $automation = $config['automation'] ?? null;

        if (! is_string($automation) || $automation === '') {
            throw new RuntimeException('No automation configured for this integration step.');
        }

        // Enrolment needs the destination's own id, so the contact has to exist
        // there first. Doing the upsert here rather than making the operator
        // build two steps keeps "add them to the follow-up sequence" one action.
        $this->require($driver, SyncsContacts::class, $instance, 'sync contacts');
        $remoteId = $driver->upsertContact($instance, $contact);
        $this->rememberIdentity($instance, $context, $remoteId);

        $driver->enroll($instance, $remoteId, $automation);

        return ['remote_id' => $remoteId, 'automation' => $automation];
    }

    /**
     * Add the identity fields the operator did not map explicitly.
     *
     * A destination cannot store a person with nothing to key them by, so these
     * are implied rather than required — but they are implied AS MAPPINGS, so
     * they pass the same classification check as everything else. An operator
     * who did map them keeps their own destination names and their own `on_phi`
     * choice; nothing here overrides an explicit row.
     *
     * @param  list<array<string, mixed>>  $mappings
     * @return list<array<string, mixed>>
     */
    private function withIdentity(array $mappings): array
    {
        $mapped = array_column($mappings, 'source');

        foreach (['email', 'phone', 'first_name', 'last_name'] as $field) {
            if (! in_array($field, $mapped, true)) {
                $mappings[] = ['source' => $field, 'destination' => $field];
            }
        }

        return $mappings;
    }

    private function instance(array $config): IntegrationInstance
    {
        $slug = $config['integration'] ?? null;

        if (! is_string($slug) || $slug === '') {
            throw new RuntimeException('No integration configured for this step.');
        }

        $instance = IntegrationInstance::query()->where('slug', $slug)->first();

        if ($instance === null) {
            throw new RuntimeException("Integration [{$slug}] no longer exists.");
        }

        if (! $instance->is_active) {
            throw new RuntimeException("Integration [{$instance->name}] is switched off.");
        }

        return $instance;
    }

    /** @param  class-string  $contract */
    private function require(object $driver, string $contract, IntegrationInstance $instance, string $verb): void
    {
        if (! $driver instanceof $contract) {
            throw new RuntimeException(
                "[{$instance->name}] cannot {$verb}. This is a limit of the provider, not a "
                .'configuration problem — choose a different operation or a different integration.'
            );
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
