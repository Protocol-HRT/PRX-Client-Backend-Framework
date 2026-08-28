<?php

namespace Tests\Feature\Integrations;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\Contracts\EnrollsInAutomations;
use App\Integrations\Contracts\SendsSms;
use App\Integrations\Contracts\SyncsContacts;
use App\Integrations\Contracts\TracksEvents;
use App\Integrations\IntegrationRegistry;
use App\Integrations\Messages\ContactPayload;
use App\Integrations\Messages\SmsMessage;
use App\Models\Integrations\IntegrationInstance;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowAction;
use App\Workflows\Actions\SendSmsAction;
use App\Workflows\WorkflowRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The catalogue, the capability derivation, and the palette that reads them.
 *
 * The two fakes below are the point of the design rather than test scaffolding:
 * they are deliberately shaped like Klaviyo and GoHighLevel, which are
 * mechanically opposite — one has events and forbids direct enrolment, the other
 * has no events API and allows it. A contract that only ever sees one vendor is
 * not yet a contract, so both shapes are exercised here.
 */
class IntegrationRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_drivers_capabilities_are_derived_from_the_code_not_declared(): void
    {
        // The registration below says nothing about capabilities. If a driver
        // could declare them, a driver that lost an interface would keep
        // claiming it, and the failure would surface in an operator's funnel.
        $this->registry()->registerProvider('fake_events', EventsOnlyDriver::class, 'Events only');

        $this->assertSame(
            [IntegrationCapability::Crm],
            $this->registry()->capabilitiesFor('fake_events'),
        );
    }

    public function test_two_vendors_with_opposite_shapes_both_fit_the_contract(): void
    {
        // The failure this design exists to avoid: one interface wide enough for
        // both, with each implementation throwing on the half it cannot do.
        $registry = $this->registry();
        $registry->registerProvider('fake_events', EventsOnlyDriver::class, 'Events only');
        $registry->registerProvider('fake_enrol', EnrolOnlyDriver::class, 'Enrolment only');

        $this->assertTrue(is_a(EventsOnlyDriver::class, TracksEvents::class, true));
        $this->assertFalse(is_a(EventsOnlyDriver::class, EnrollsInAutomations::class, true));

        $this->assertTrue(is_a(EnrolOnlyDriver::class, EnrollsInAutomations::class, true));
        $this->assertFalse(is_a(EnrolOnlyDriver::class, TracksEvents::class, true));

        // Both are still CRMs, so both are offered for a contact sync.
        $this->assertContains(IntegrationCapability::Crm, $registry->capabilitiesFor('fake_events'));
        $this->assertContains(IntegrationCapability::Crm, $registry->capabilitiesFor('fake_enrol'));
    }

    public function test_an_unregistered_provider_resolves_to_nothing_rather_than_a_default(): void
    {
        $instance = IntegrationInstance::create([
            'name' => 'Ghost', 'provider' => 'not_registered', 'capabilities' => ['crm'],
        ]);

        // A destination that quietly becomes a DIFFERENT destination is worse
        // than one that fails, so this throws rather than falling back.
        $this->expectException(RuntimeException::class);
        $this->registry()->driverFor($instance);
    }

    public function test_a_capability_needs_both_the_operators_switch_and_the_drivers_code(): void
    {
        $registry = $this->registry();
        $registry->registerProvider('fake_sms', SmsOnlyDriver::class, 'SMS only');

        // Ticked by the operator, but the driver cannot do it. A stale row must
        // not promise a capability the code has lost.
        $lying = IntegrationInstance::create([
            'name' => 'Claims too much', 'provider' => 'fake_sms', 'capabilities' => ['sms', 'crm'],
        ]);

        $this->assertTrue($registry->instanceOffers($lying, IntegrationCapability::Sms));
        $this->assertFalse($registry->instanceOffers($lying, IntegrationCapability::Crm));

        // Capable, but the operator has not authorised it — their account may
        // simply not be cleared for it.
        $unticked = IntegrationInstance::create([
            'name' => 'Not switched on', 'provider' => 'fake_sms', 'capabilities' => [],
        ]);

        $this->assertFalse($registry->instanceOffers($unticked, IntegrationCapability::Sms));
    }

    public function test_an_inactive_instance_is_not_offered(): void
    {
        $this->registry()->registerProvider('fake_sms', SmsOnlyDriver::class, 'SMS only');

        IntegrationInstance::create([
            'name' => 'Switched off', 'provider' => 'fake_sms',
            'capabilities' => ['sms'], 'is_active' => false,
        ]);

        $this->assertTrue($this->registry()->instancesOffering(IntegrationCapability::Sms)->isEmpty());
    }

    public function test_an_action_is_not_offered_while_nothing_can_perform_it(): void
    {
        // A fresh install configures no SMS provider, so "Send an SMS" must not
        // appear at all. An action that can only fail is worse than an absent
        // one: the operator builds the funnel, sees no error, and finds out
        // weeks later that the step never ran.
        $this->assertArrayNotHasKey('send_sms', app(WorkflowRegistry::class)->actionOptions());

        $this->registry()->registerProvider('fake_sms', SmsOnlyDriver::class, 'SMS only');
        IntegrationInstance::create([
            'name' => 'Texts', 'provider' => 'fake_sms', 'capabilities' => ['sms'],
        ]);

        $this->assertArrayHasKey('send_sms', app(WorkflowRegistry::class)->actionOptions());
    }

    public function test_an_action_still_resolves_after_its_integration_is_switched_off(): void
    {
        // The form filters; the RUNNER does not. A workflow authored while an
        // integration was enabled must keep failing loudly per run once it is
        // switched off, rather than silently becoming a no-op.
        $this->assertArrayNotHasKey('send_sms', app(WorkflowRegistry::class)->actionOptions());

        $this->assertInstanceOf(
            SendSmsAction::class,
            app(WorkflowRegistry::class)->resolveAction('send_sms'),
        );
    }

    public function test_renaming_an_identifier_that_workflows_point_at_is_refused(): void
    {
        // A rename IS a removal when references are by name, and these live in a
        // JSON column no foreign key can protect.
        $instance = IntegrationInstance::create([
            'name' => 'Marketing', 'slug' => 'marketing', 'provider' => 'local_mail',
        ]);

        $workflow = Workflow::create([
            'name' => 'wf', 'slug' => 'wf', 'trigger_type' => 'model_updated',
            'trigger_target' => 'lead', 'conditions' => [], 'is_active' => true,
            'priority' => 0, 'stop_on_first_match' => false,
        ]);
        WorkflowAction::create([
            'workflow_id' => $workflow->id, 'action_type' => 'push_to_integration',
            'config' => ['integration' => 'marketing'], 'is_active' => true,
            'sort_order' => 0, 'halt_on_failure' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $instance->update(['slug' => 'marketing-renamed']);
    }

    public function test_deleting_an_instance_that_workflows_point_at_is_refused(): void
    {
        $instance = IntegrationInstance::create([
            'name' => 'Marketing', 'slug' => 'marketing', 'provider' => 'local_mail',
        ]);

        $workflow = Workflow::create([
            'name' => 'wf', 'slug' => 'wf', 'trigger_type' => 'model_updated',
            'trigger_target' => 'lead', 'conditions' => [], 'is_active' => true,
            'priority' => 0, 'stop_on_first_match' => false,
        ]);
        WorkflowAction::create([
            'workflow_id' => $workflow->id, 'action_type' => 'push_to_integration',
            'config' => ['integration' => 'marketing'], 'is_active' => true,
            'sort_order' => 0, 'halt_on_failure' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $instance->delete();
    }

    public function test_an_unreferenced_instance_can_still_be_renamed_and_removed(): void
    {
        // The guard must not become a reason nobody can tidy up.
        $instance = IntegrationInstance::create([
            'name' => 'Spare', 'slug' => 'spare', 'provider' => 'local_mail',
        ]);

        $instance->update(['slug' => 'spare-renamed']);
        $this->assertSame('spare-renamed', $instance->fresh()->slug);

        $instance->delete();
        $this->assertNull(IntegrationInstance::query()->where('slug', 'spare-renamed')->first());
    }

    private function registry(): IntegrationRegistry
    {
        return app(IntegrationRegistry::class);
    }
}

/** Shaped like Klaviyo: events yes, direct enrolment no. */
class EventsOnlyDriver implements SyncsContacts, TracksEvents
{
    public function test(IntegrationInstance $instance): void {}

    public function upsertContact(IntegrationInstance $instance, ContactPayload $contact): string
    {
        return 'remote-1';
    }

    public function addToGroup(IntegrationInstance $instance, string $remoteId, string $group, ContactPayload $contact): void {}

    public function trackEvent(IntegrationInstance $instance, ContactPayload $contact, string $event, array $properties = []): array
    {
        return ['event' => $event];
    }
}

/** Shaped like GoHighLevel: enrolment yes, events do not exist. */
class EnrolOnlyDriver implements EnrollsInAutomations, SyncsContacts
{
    public function test(IntegrationInstance $instance): void {}

    public function upsertContact(IntegrationInstance $instance, ContactPayload $contact): string
    {
        return 'remote-2';
    }

    public function addToGroup(IntegrationInstance $instance, string $remoteId, string $group, ContactPayload $contact): void {}

    public function enroll(IntegrationInstance $instance, string $remoteId, string $automation): void {}
}

class SmsOnlyDriver implements SendsSms
{
    public function test(IntegrationInstance $instance): void {}

    public function sendSms(IntegrationInstance $instance, SmsMessage $message): array
    {
        return ['sid' => 'SM123'];
    }
}
