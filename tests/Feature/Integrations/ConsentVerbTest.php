<?php

namespace Tests\Feature\Integrations;

use App\Integrations\ConsentResolver;
use App\Integrations\Contracts\SyncsContacts;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Messages\ContactPayload;
use App\Models\Integrations\IntegrationIdentity;
use App\Models\Integrations\IntegrationInstance;
use App\Models\Lead;
use App\Models\LeadConsent;
use App\Workflows\Actions\PushToIntegrationAction;
use App\Workflows\WorkflowContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which verb a destination is given, and where that decision comes from.
 *
 * WHAT MAKES THIS WORTH PINNING is that both wrong answers are quiet. Adding
 * somebody to a list without consent produces a successful run and a suppressed
 * send — the flow fires, the email never lands, and nothing reports it.
 * Subscribing somebody who never agreed produces a successful run and a
 * complaint to a regulator. Neither shows up as a failure, so the correctness
 * has to live in tests rather than in whether the push "worked".
 *
 * The rule under all of it: the verb comes from OUR consent audit, never from
 * anything an operator mapped and never from the destination's own state.
 */
class ConsentVerbTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lead_with_no_consent_rows_has_consented_to_nothing(): void
    {
        // Silence is not permission. The absence of a refusal is the state every
        // lead starts in, and reading it as consent would opt in everybody who
        // ever filled anything in.
        $lead = Lead::factory()->create();

        $this->assertFalse(app(ConsentResolver::class)->resolve($lead)->grantsAnything());
    }

    public function test_the_latest_row_per_channel_wins_so_a_withdrawal_takes_effect(): void
    {
        // `lead_consents` is append-only: a withdrawal is a NEW row, not an edit.
        // Read it wrong and a person who opted out stays subscribed forever,
        // which is the failure that carries a fine.
        $lead = Lead::factory()->create();

        $this->consent($lead, 'email', granted: true, at: now()->subDay());
        $this->consent($lead, 'sms', granted: true, at: now()->subDay());
        $this->consent($lead, 'email', granted: false, at: now());

        $state = app(ConsentResolver::class)->resolve($lead);

        $this->assertFalse($state->grants('email'));
        $this->assertTrue($state->grants('sms'));
    }

    public function test_consent_is_resolved_at_send_time_not_from_the_trigger_snapshot(): void
    {
        // A chain carries an attribute snapshot from when the trigger fired and
        // may run well after it. Consent is exactly the field where the stale
        // answer is the harmful one, so it is queried rather than remembered —
        // the same rule the PHI attestation already follows.
        $lead = Lead::factory()->create();
        $this->consent($lead, 'email', granted: true, at: now()->subHour());

        $context = $this->context($lead);

        // Withdrawn after the context was built, exactly as a withdrawal landing
        // while the job sat on the queue would be.
        $this->consent($lead, 'email', granted: false, at: now());

        $this->assertFalse(
            app(ConsentResolver::class)->resolve($context->subject)->grants('email'),
        );
    }

    public function test_a_subject_that_is_not_a_person_consents_to_nothing(): void
    {
        // Fails closed. A workflow whose subject is an order or a null cannot
        // have a consent record, and the safe reading of that is silence.
        $this->assertFalse(app(ConsentResolver::class)->resolve(null)->grantsAnything());
    }

    public function test_the_cached_boolean_cannot_grant_anything_on_its_own(): void
    {
        // `leads.email_consent` is a cache of the audit, not a second opinion. A
        // boolean with no record behind it is precisely what the audit exists to
        // stop being treated as consent — and a path that can set it without
        // writing a row is a bug in that path, not a state to honour here.
        $lead = Lead::factory()->create();
        $lead->forceFill(['email_consent' => true])->save();

        $this->assertFalse(app(ConsentResolver::class)->resolve($lead->fresh())->grants('email'));
    }

    public function test_an_unrecognised_when_not_consented_value_still_skips(): void
    {
        // `config` is JSON and the form is not its only author. A typo reading as
        // "add them anyway" would opt somebody in through a spelling mistake.
        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);
        $driver = $this->registerRecordingDriver();

        $this->push($lead, ['group' => 'quiz-completers', 'when_not_consented' => 'Skip']);

        $this->assertSame([], $driver::$groups);
    }

    public function test_a_non_consented_lead_is_left_off_the_list_by_default(): void
    {
        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);
        $driver = $this->registerRecordingDriver();

        $result = $this->push($lead, ['group' => 'quiz-completers']);

        $this->assertSame([], $driver::$groups);
        $this->assertSame('no marketing consent on record', $result['group_skipped']);
    }

    public function test_the_skip_is_named_in_the_run_result_rather_than_silent(): void
    {
        // A step that quietly does three-quarters of its job is the failure this
        // whole slice exists to remove. The operator has to be able to answer
        // "why is this person not on the list" from the run log alone.
        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);
        $this->registerRecordingDriver();

        $result = $this->push($lead, ['group' => 'quiz-completers']);

        $this->assertArrayHasKey('group_skipped', $result);
        $this->assertArrayNotHasKey('consented', $result);
    }

    public function test_a_consented_lead_reaches_the_group_with_the_consent_attached(): void
    {
        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);
        $this->consent($lead, 'email', granted: true, at: now());

        $driver = $this->registerRecordingDriver();

        $result = $this->push($lead, ['group' => 'quiz-completers']);

        $this->assertCount(1, $driver::$groups);
        $this->assertSame(['email'], $driver::$groups[0]['consent']);
        $this->assertSame(['email'], $result['consented']);
    }

    public function test_an_operator_can_still_add_a_non_consented_lead_to_an_internal_tag(): void
    {
        // Not every group is an audience. A CRM tag such as "quiz-abandoned" is
        // bookkeeping, and refusing to apply it would break a legitimate funnel —
        // so the override exists. It is not the default.
        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);
        $driver = $this->registerRecordingDriver();

        $this->push($lead, [
            'group' => 'quiz-abandoned',
            'when_not_consented' => PushToIntegrationAction::NOT_CONSENTED_ADD,
        ]);

        $this->assertCount(1, $driver::$groups);
        $this->assertSame([], $driver::$groups[0]['consent']);
    }

    public function test_consent_cannot_be_forged_through_a_field_mapping(): void
    {
        // Consent is an invariant, not something an operator chose to send. A
        // mapping whose destination is called "consent" must be nothing more
        // than a custom property with a suggestive name.
        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);
        $driver = $this->registerRecordingDriver();

        $result = $this->push($lead, [
            'group' => 'quiz-completers',
            'mappings' => [['source' => 'utm_source', 'destination' => 'consent']],
        ]);

        $this->assertSame([], $driver::$groups);
        $this->assertSame('no marketing consent on record', $result['group_skipped']);
    }

    public function test_the_remote_id_is_remembered_and_a_repush_updates_it(): void
    {
        // `upsertContact()` returns the destination's own id and it used to be
        // thrown away, leaving nothing to reconcile against later. One row per
        // (instance, subject): a re-push refreshes rather than duplicating,
        // because a vendor merging two profiles hands back a different id.
        $lead = Lead::factory()->create(['email' => 'someone@example.invalid']);
        $driver = $this->registerRecordingDriver();

        $instance = $this->destination();
        $this->push($lead, [], $instance);

        $driver::$remoteId = 'remote-after-merge';
        $this->push($lead, [], $instance);

        $this->assertSame(1, IntegrationIdentity::query()->count());
        $this->assertSame('remote-after-merge', IntegrationIdentity::query()->value('remote_id'));
    }

    /** @return class-string<RecordingCrmDriver> */
    private function registerRecordingDriver(): string
    {
        RecordingCrmDriver::$groups = [];
        RecordingCrmDriver::$remoteId = 'remote-1';

        app(IntegrationRegistry::class)
            ->registerProvider('recording_crm', RecordingCrmDriver::class, 'Recording CRM');

        return RecordingCrmDriver::class;
    }

    private function destination(): IntegrationInstance
    {
        return IntegrationInstance::create([
            'name' => 'Marketing platform',
            'provider' => 'recording_crm',
            'capabilities' => ['crm'],
        ]);
    }

    /** @param  array<string, mixed>  $config */
    private function push(Lead $lead, array $config, ?IntegrationInstance $instance = null): array
    {
        $instance ??= $this->destination();

        return app(PushToIntegrationAction::class)->handle(
            $this->context($lead),
            ['integration' => $instance->slug, 'operation' => 'sync_contact'] + $config,
        );
    }

    private function context(Lead $lead): WorkflowContext
    {
        return new WorkflowContext(
            triggerType: 'model_created',
            triggerTarget: 'lead',
            subject: $lead,
            subjectKey: 'lead',
        );
    }

    private function consent(Lead $lead, string $channel, bool $granted, $at): void
    {
        LeadConsent::create([
            'lead_id' => $lead->id,
            'channel' => $channel,
            'granted' => $granted,
            'source' => 'quiz',
            'consented_at' => $at,
        ]);
    }
}

/** Records what it was asked to do, so the verb itself can be asserted. */
class RecordingCrmDriver implements SyncsContacts
{
    /** @var list<array{group: string, consent: list<string>}> */
    public static array $groups = [];

    public static string $remoteId = 'remote-1';

    public function test(IntegrationInstance $instance): void {}

    public function upsertContact(IntegrationInstance $instance, ContactPayload $contact): string
    {
        return static::$remoteId;
    }

    public function addToGroup(IntegrationInstance $instance, string $remoteId, string $group, ContactPayload $contact): void
    {
        static::$groups[] = [
            'group' => $group,
            'consent' => $contact->consent?->granted ?? [],
        ];
    }
}
