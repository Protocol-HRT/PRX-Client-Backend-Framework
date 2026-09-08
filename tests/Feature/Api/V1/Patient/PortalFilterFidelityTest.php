<?php

namespace Tests\Feature\Api\V1\Patient;

use App\Models\Patient;
use App\Services\Patient\PortalResponseFilter;
use App\Services\PrescribeRx\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The other half of the allowlist contract: the fields a screen NEEDS must
 * survive it.
 *
 * PortalLeakAndOwnershipTest proves nothing leaks. That is only half a filter —
 * an empty allowlist passes every one of those assertions. Two specs shipped
 * transcribed from the wrong PRX endpoint's DTO and stripped weight, blood
 * pressure and the entire dose out of their screens, while the filter reported
 * success and the leak tests stayed green.
 *
 * So: one fixture per screen, copied from the PRX HANDLER at prx-demo@07969f8
 * — never from a resource DTO, because several endpoints have a resource whose
 * field names differ from the array the `/me/patient/*` handler actually
 * builds, and the vitals endpoint uses one set of names on the way in and
 * another on the way out.
 */
class PortalFilterFidelityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Sanctum::actingAs(Patient::factory()->withPrxChart()->create(), ['*']);
    }

    private function mockPrx(callable $expectations): void
    {
        $this->mock(Client::class, function ($mock) use ($expectations): void {
            $mock->shouldReceive('issuePatientToken')->andReturn([
                'token' => 'patient-token',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ]);
            $expectations($mock);
        });
    }

    /** Exactly the array `PatientSelfServiceController::formatVital` returns (:1351-1373). */
    private function vitalFixture(): array
    {
        return [
            'id' => 'vit-1',
            'weight' => 197.0,
            'height' => 70.0,
            'bmi' => 28.3,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 64,
            'temperature' => 98.6,
            'respiratory_rate' => 14,
            'oxygen_saturation' => 98,
            'blood_glucose' => 91.0,
            'glucose_timing' => 'fasting',
            'pain_level' => 0,
            'pain_location' => null,
            'source' => 'patient',
            'notes' => 'felt fine',
            'is_abnormal' => false,
            'created_at' => '2026-09-05T12:00:00+00:00',
        ];
    }

    public function test_a_vital_keeps_the_numbers_the_health_screen_exists_to_show(): void
    {
        $this->mockPrx(fn ($mock) => $mock->shouldReceive('getPatientVitals')->andReturn([$this->vitalFixture()]));

        $body = $this->getJson('/api/v1/patient/vitals')->assertOk()->json('data.0');

        // If any of these three go missing the screen renders a row of blanks,
        // which is precisely how the original defect looked in a browser.
        $this->assertEquals(197.0, $body['weight']);
        $this->assertEquals(70.0, $body['height']);
        $this->assertSame(120, $body['systolic_bp']);
        $this->assertSame(80, $body['diastolic_bp']);
        $this->assertEquals(28.3, $body['bmi']);
    }

    public function test_recording_a_vital_returns_the_change_since_last_time(): void
    {
        $this->mockPrx(fn ($mock) => $mock->shouldReceive('recordPatientVital')->andReturn([
            ...$this->vitalFixture(),
            'change_from_last' => ['weight' => -2.4, 'period_days' => 7],
        ]));

        $body = $this->postJson('/api/v1/patient/vitals', ['weight_lbs' => 197])
            ->assertCreated()
            ->json('data');

        $this->assertEquals(197.0, $body['weight']);
        $this->assertEquals(-2.4, $body['change_from_last']['weight']);
        $this->assertSame(7, $body['change_from_last']['period_days']);
    }

    public function test_a_prescription_keeps_the_dose_which_is_nested_under_items(): void
    {
        // The whole reason a patient opens this screen. It lives at
        // items[].sig, not at the top level (prx-demo :645-672).
        $this->mockPrx(fn ($mock) => $mock->shouldReceive('getPatientPrescriptions')->andReturn([[
            'id' => 'rx-1',
            'prescription_number' => 'RX-1',
            'status' => 'active',
            'prescribed_at' => '2026-08-01T00:00:00+00:00',
            'expires_at' => '2027-08-01T00:00:00+00:00',
            'refills' => 2,
            'prescriber' => 'Dr Reyes',
            'clinical_notes' => 'internal — audience undetermined',
            'items' => [[
                'product_name' => 'Semaglutide 5 mg',
                'sig' => 'Inject 20 units subcutaneously once weekly',
                'quantity' => 1,
                'days_supply' => 28,
                'refills' => 2,
                'patient_instructions' => 'Rotate injection sites.',
                'titration' => [[
                    'step_order' => 1,
                    'label' => 'Week 1-4',
                    'start_day' => 1,
                    'end_day' => 28,
                    'dose_amount' => 0.25,
                    'dose_unit' => 'mg',
                    'frequency' => 'Weekly',
                    'instructions' => 'Start here.',
                ]],
            ]],
        ]]));

        $body = $this->getJson('/api/v1/patient/prescriptions')->assertOk()->json('data.0');

        $this->assertSame('Dr Reyes', $body['prescriber']);
        $this->assertSame('Semaglutide 5 mg', $body['items'][0]['product_name']);
        $this->assertSame('Inject 20 units subcutaneously once weekly', $body['items'][0]['sig']);
        $this->assertSame('Rotate injection sites.', $body['items'][0]['patient_instructions']);
        $this->assertEquals(0.25, $body['items'][0]['titration'][0]['dose_amount']);
        $this->assertSame('mg', $body['items'][0]['titration'][0]['dose_unit']);

        // Held back until PRX confirms the audience.
        $this->assertArrayNotHasKey('clinical_notes', $body);
    }

    public function test_a_conversation_keeps_its_unread_count_and_last_message(): void
    {
        $this->mockPrx(fn ($mock) => $mock->shouldReceive('getPatientConversations')->andReturn([[
            'id' => 'conv-1',
            'type' => 'encounter',
            'subject' => 'GLP-1 follow-up',
            'encounter_id' => 'enc-1',
            'encounter_number' => 'ENC-1',
            'unread_count' => 2,
            'updated_at' => '2026-09-06T10:00:00+00:00',
            'participants' => [['participant_type' => 'provider', 'name' => 'Dr Reyes', 'user_id' => 'usr-internal']],
            'last_message' => ['content' => 'How are you getting on?', 'created_at' => '2026-09-06T10:00:00+00:00', 'sender_id' => 'usr-internal'],
        ]]));

        $body = $this->getJson('/api/v1/patient/conversations')->assertOk()->json('data.0');

        $this->assertSame(2, $body['unread_count']);
        $this->assertSame('How are you getting on?', $body['last_message']['content']);
        $this->assertSame('Dr Reyes', $body['participants'][0]['name']);

        // Internal PRX user ids are not the patient's to hold.
        $this->assertArrayNotHasKey('sender_id', $body['last_message']);
        $this->assertArrayNotHasKey('user_id', $body['participants'][0]);
    }

    public function test_an_empty_conversation_keeps_a_null_last_message_rather_than_an_empty_object(): void
    {
        // Coercing null to [] would read as "a message with no fields", and the
        // portal branches on the null.
        $this->mockPrx(fn ($mock) => $mock->shouldReceive('getPatientConversations')->andReturn([[
            'id' => 'conv-1',
            'unread_count' => 0,
            'last_message' => null,
        ]]));

        $body = $this->getJson('/api/v1/patient/conversations')->assertOk()->json('data.0');

        $this->assertNull($body['last_message']);
    }

    public function test_slots_survive_being_a_list_nested_inside_a_list(): void
    {
        $this->mockPrx(fn ($mock) => $mock->shouldReceive('getAvailabilitySlots')->andReturn([
            'date_range' => ['start' => '2026-09-08', 'end' => '2026-09-15'],
            'total_slots' => 1,
            'has_valid_labs' => true,
            'lab_offset_days' => 0,
            'slots' => [[
                'date' => '2026-09-08',
                'display' => 'Mon 8 Sep',
                'slot_count' => 1,
                'slots' => [[
                    'start' => '2026-09-08T09:00:00+00:00',
                    'end' => '2026-09-08T09:15:00+00:00',
                    'display_time' => '9:00 AM',
                    'provider_profile_id' => 'prov-1',
                    'timezone' => 'America/New_York',
                ]],
            ]],
        ]));

        $body = $this->getJson('/api/v1/patient/scheduling/slots?encounter_type_id=019d2842-0000-4000-8000-00000000abcd')
            ->assertOk()
            ->json('data');

        // The booking handle has to survive two levels of nesting or nothing
        // can be booked.
        $this->assertSame('prov-1', $body['slots'][0]['slots'][0]['provider_profile_id']);
        $this->assertSame('9:00 AM', $body['slots'][0]['slots'][0]['display_time']);
        $this->assertSame('2026-09-15', $body['date_range']['end']);
    }

    public function test_an_unregistered_screen_fails_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PortalResponseFilter::class)->apply('not-a-screen', ['a' => 1]);
    }
}
