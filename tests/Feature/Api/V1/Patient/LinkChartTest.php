<?php

namespace Tests\Feature\Api\V1\Patient;

use App\Models\Commerce\Encounter;
use App\Models\Lead;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Claiming the medical record an order created.
 *
 * This is the step that was missing entirely: the only runtime writer of
 * `patients.prx_patient_chart_id` was a Filament text input, so every real
 * patient needed an operator to paste a chart id before the portal would show
 * them anything.
 *
 * The refusals matter more than the happy path here. `leads.prescribe_rx_patient_id`
 * is written by two paths — our own checkout (which reads the id out of the
 * provider's response) and `LeadIntakeController::complete`, which is
 * `@unauthenticated` and takes it from the request body. Linking straight from
 * that column would let anyone holding a lead uuid attach a chart of their
 * choosing to their own account.
 */
class LinkChartTest extends TestCase
{
    use RefreshDatabase;

    private const CHART = '01a07ea4-282e-70d1-b350-6fc58731c738';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * A lead that has actually been through checkout: an encounter exists, and
     * it carries the chart id our server read out of the provider's response.
     * `$chartOnEncounter = null` is a lead that reached the provider but has no
     * chart back yet.
     */
    private function lead(array $attributes = [], ?string $chartOnEncounter = self::CHART): Lead
    {
        $lead = Lead::factory()->create(array_merge([
            'email' => 'buyer@example.test',
            'patient_id' => null,
            'prescribe_rx_patient_id' => self::CHART,
        ], $attributes));

        if ($chartOnEncounter !== null) {
            Encounter::factory()->create([
                'lead_id' => $lead->id,
                'prescribe_rx_patient_id' => $chartOnEncounter,
            ]);
        }

        return $lead;
    }

    /** A lead anyone could have minted: no encounter, so no evidence. */
    private function selfMintedLead(array $attributes = []): Lead
    {
        return $this->lead($attributes, chartOnEncounter: null);
    }

    private function actingAsPatient(array $attributes = []): Patient
    {
        $patient = Patient::factory()->create(array_merge([
            'email' => 'buyer@example.test',
            'prx_patient_chart_id' => null,
        ], $attributes));

        Sanctum::actingAs($patient, ['*']);

        return $patient;
    }

    public function test_a_patient_can_claim_the_record_their_own_order_created(): void
    {
        $patient = $this->actingAsPatient();
        $lead = $this->lead();

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertOk()
            ->assertJsonPath('data.patient.has_prx_chart', true);

        $patient->refresh();

        $this->assertSame(self::CHART, $patient->prx_patient_chart_id);
        // Stamped only on this path: it means a server-side check agreed, which
        // is exactly what the Filament text input cannot say.
        $this->assertNotNull($patient->prx_chart_verified_at);
        $this->assertSame($patient->id, $lead->refresh()->patient_id);
    }

    public function test_a_self_minted_lead_cannot_claim_anyones_record(): void
    {
        // 🔴 THE REGRESSION THIS FILE EXISTS FOR. `POST /leads` is anonymous and
        // returns the uuid — it is the checkout form itself — so a lead is NOT
        // proof of a transaction. An earlier version of this action resolved the
        // chart from the lead's EMAIL, which made the whole thing a takeover:
        // register the victim's address (nothing verifies it), mint a lead with
        // that address, claim it, receive their clinical record.
        //
        // An encounter cannot be minted. Its only writers are our own checkout
        // and the HMAC-verified webhook.
        $this->actingAsPatient();
        $lead = $this->selfMintedLead();

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertStatus(422);

        $this->assertDatabaseHas('patients', ['email' => 'buyer@example.test', 'prx_patient_chart_id' => null]);
    }

    public function test_the_chart_id_planted_on_the_lead_is_ignored_in_favour_of_the_encounter(): void
    {
        // `LeadIntakeController::complete` and `EmbedCompleteController` both
        // write `leads.prescribe_rx_patient_id` with no credential, from the
        // request body. The encounter is what gets linked.
        $this->actingAsPatient();
        $lead = $this->lead(['prescribe_rx_patient_id' => 'planted-by-an-attacker']);

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertOk();

        $this->assertDatabaseHas('patients', [
            'email' => 'buyer@example.test',
            'prx_patient_chart_id' => self::CHART,
        ]);
    }

    public function test_a_lead_belonging_to_someone_else_is_refused(): void
    {
        // The lead uuid rides in the intake URL, so it leaks. On its own it must
        // not be enough to attach another person's clinical record.
        $this->actingAsPatient(['email' => 'attacker@example.test']);
        $lead = $this->lead(['email' => 'victim@example.test']);

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertStatus(422)
            // Character-for-character the refusal an unknown uuid gets, so a
            // caller cannot tell "no such order" from "not yours".
            ->assertJsonPath('errors.lead_uuid.0', 'That order does not belong to this account.');

        $this->assertDatabaseHas('patients', ['email' => 'attacker@example.test', 'prx_patient_chart_id' => null]);
    }

    public function test_a_chart_already_held_by_another_account_is_refused(): void
    {
        Patient::factory()->create(['email' => 'first@example.test', 'prx_patient_chart_id' => self::CHART]);

        $this->actingAsPatient();
        $lead = $this->lead();

        // Would otherwise be a 500 on the unique index. Assert the MESSAGE:
        // without it a future guard firing earlier keeps this green while never
        // exercising the branch it names.
        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertStatus(422)
            ->assertJsonPath('errors.lead_uuid.0', 'That medical record is already linked to another account.');
    }

    public function test_an_already_claimed_lead_is_refused(): void
    {
        $other = Patient::factory()->create(['email' => 'other@example.test']);

        $this->actingAsPatient();
        $lead = $this->lead(['patient_id' => $other->id]);

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertStatus(422);
    }

    public function test_relinking_an_account_that_already_has_a_record_is_refused(): void
    {
        // Re-linking would silently move a patient's clinical history onto a
        // different chart. That is an operator decision, never a self-serve one.
        $this->actingAsPatient(['prx_patient_chart_id' => 'existing-chart']);
        $lead = $this->lead();

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertStatus(422);

        $this->assertDatabaseHas('patients', ['email' => 'buyer@example.test', 'prx_patient_chart_id' => 'existing-chart']);
    }

    public function test_an_encounter_without_a_chart_yet_is_refused_not_guessed(): void
    {
        // The consultation reached the provider but no chart has come back.
        // Note the lead still carries a chart id: falling back to it — which is
        // the mutation this pins — would link an unverified value.
        $this->actingAsPatient();
        $lead = $this->lead(['prescribe_rx_patient_id' => self::CHART], chartOnEncounter: null);
        Encounter::factory()->create(['lead_id' => $lead->id, 'prescribe_rx_patient_id' => null]);

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertStatus(422);

        $this->assertDatabaseHas('patients', ['email' => 'buyer@example.test', 'prx_patient_chart_id' => null]);
    }

    public function test_re_claiming_the_same_lead_is_a_no_op_not_an_error(): void
    {
        // The idempotency the docblock promises, which nothing pinned before.
        $patient = $this->actingAsPatient();
        $lead = $this->lead();

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])->assertOk();
        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])->assertOk();

        $this->assertSame(self::CHART, $patient->refresh()->prx_patient_chart_id);
    }

    public function test_an_unknown_lead_is_indistinguishable_from_someone_elses(): void
    {
        $this->actingAsPatient();

        // Whether a uuid exists is not something a caller should learn from us.
        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => '019d5561-7b24-7096-baa7-85485643b0a7'])
            ->assertStatus(422)
            ->assertJsonPath('errors.lead_uuid.0', 'That order does not belong to this account.');
    }

    public function test_the_endpoint_requires_a_patient_session(): void
    {
        $lead = $this->lead();

        $this->postJson('/api/v1/patient/link-chart', ['lead_uuid' => $lead->uuid])
            ->assertUnauthorized();
    }
}
