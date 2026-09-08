<?php

namespace Tests\Feature\PrescribeRx;

use App\Actions\Patient\IssuePortalTokenAction;
use App\Models\Patient;
use App\Services\PrescribeRx\Client;
use App\Services\PrescribeRx\Exceptions\PrescribeRxException;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The patient-portal token mint, asserted on the JSON actually put on the wire.
 *
 * Both defects these tests pin were invisible from inside this app. We sent
 * `token_name`; PRX validates `device_name` as required (IssuePatientTokenData,
 * prx-demo@07969f8) and 422s the whole mint, so no portal session could ever
 * start. And `IssuePortalTokenAction` requested nine of PRX's thirteen PATIENT
 * abilities — the four omissions do not fail here at all, they 403 later at the
 * approvals, scheduling and lab endpoints that need them.
 *
 * So these assert the REQUEST BODY, not that a token came back: a stub or a
 * lenient fake returns a token either way, which is exactly how both shipped.
 */
class PortalTokenContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PRX's own PATIENT set — TokenAbility::forUserType(UserType::PATIENT),
     * prx-demo@07969f8 app/Enums/Api/TokenAbility.php:159-172. Transcribed here
     * rather than imported, because PRX is a separate deployment: this is the
     * contract we believe holds, and a drift should fail here loudly.
     */
    private const PRX_PATIENT_ABILITIES = [
        'patient:read',
        'patient:update',
        'patient:approve-charge',
        'patient:vitals',
        'order:read',
        'telehealth:read',
        'telehealth:submit',
        'encounter:read',
        'prescription:read',
        'product:read',
        'scheduling:read',
        'scheduling:write',
        'lab:read',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('prescribe-rx.stub', false);

        $settings = app(IntegrationSettings::class);
        $settings->prescribe_rx_enabled = true;
        $settings->prescribe_rx_api_token = 'test-org-token';
        $settings->prescribe_rx_environment = 'sandbox';

        Http::fake(['*/patients/*/issue-token' => Http::response([
            'data' => [
                'token' => 'plain-text-patient-token',
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'abilities' => self::PRX_PATIENT_ABILITIES,
                'patient_id' => '019d5561-7b24-7096-baa7-85485643b0a7',
                'patient_chart_id' => '019d5561-7b2b-7096-8d90-4ee49ef2ede8',
            ],
        ], 201)]);
    }

    private function patient(): Patient
    {
        return Patient::factory()->withPrxChart()->create();
    }

    /** @return array<string, mixed> The body of the single issue-token request. */
    private function sentBody(): array
    {
        $body = null;

        Http::recorded(function ($request) use (&$body) {
            if (str_contains($request->url(), '/issue-token')) {
                $body = $request->data();
            }

            return true;
        });

        $this->assertIsArray($body, 'No issue-token request was put on the wire.');

        return $body;
    }

    public function test_the_mint_sends_device_name_and_never_token_name(): void
    {
        app(IssuePortalTokenAction::class)->execute($this->patient());

        $body = $this->sentBody();

        $this->assertArrayHasKey('device_name', $body);
        $this->assertNotSame('', (string) $body['device_name']);
        $this->assertArrayNotHasKey('token_name', $body, 'token_name is the field PRX rejects.');
    }

    public function test_the_device_name_fits_the_field_prx_validates(): void
    {
        app(IssuePortalTokenAction::class)->execute($this->patient());

        // IssuePatientTokenData: #[Required, Max(120)].
        $this->assertLessThanOrEqual(120, mb_strlen((string) $this->sentBody()['device_name']));
    }

    public function test_the_mint_requests_every_ability_the_portal_needs(): void
    {
        app(IssuePortalTokenAction::class)->execute($this->patient());

        $requested = $this->sentBody()['abilities'] ?? [];

        // Named individually: each was omitted, and each cost one feature.
        foreach (['patient:approve-charge', 'scheduling:read', 'scheduling:write', 'lab:read'] as $ability) {
            $this->assertContains($ability, $requested, "Portal token omits {$ability}.");
        }
    }

    public function test_the_requested_set_matches_prx_patient_scope_exactly(): void
    {
        app(IssuePortalTokenAction::class)->execute($this->patient());

        $requested = $this->sentBody()['abilities'] ?? [];

        // Under-broad 403s a screen later; over-broad is worse than useless —
        // resolveAbilities() rejects the whole request with a 422, so a single
        // stray entry breaks every mint. Equality is the only safe assertion.
        sort($requested);
        $expected = self::PRX_PATIENT_ABILITIES;
        sort($expected);

        $this->assertSame($expected, $requested);
    }

    public function test_the_ownership_probe_uses_the_patient_token_not_the_org_token(): void
    {
        // This is the whole security value of the probe. Swapping it to the
        // sales-org token leaves every controller test passing — PRX answers 200
        // for any encounter in the org — while silently reopening the
        // cross-patient booking hole. So the BEARER is what gets asserted.
        Http::fake([
            '*/encounters/*' => Http::response(['data' => ['id' => 'enc-1', 'patient_chart_id' => 'chart-1']], 200),
        ]);

        app(Client::class)->findPatientEncounter('a-patient-scoped-token', 'enc-1');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer a-patient-scoped-token'));
        Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-org-token'));
    }

    public function test_the_probe_returns_null_when_prx_hides_the_encounter(): void
    {
        // A 404 under PRX's patient scope IS the answer "not yours" — it must
        // come back as null to branch on, never as an exception that a caller
        // might catch and treat as a transport hiccup.
        foreach ([404, 403] as $status) {
            Http::fake(['*/encounters/*' => Http::response(['message' => 'Not found.'], $status)]);

            $this->assertNull(
                app(Client::class)->findPatientEncounter('patient-token', 'enc-1'),
                "A {$status} must resolve to null."
            );
        }
    }

    public function test_the_probe_still_throws_on_an_upstream_failure(): void
    {
        // A 500 is NOT "not yours". Collapsing it to null would turn an outage
        // into a silent, universal refusal to book.
        Http::fake(['*/encounters/*' => Http::response(['message' => 'boom'], 500)]);

        $this->expectException(PrescribeRxException::class);

        app(Client::class)->findPatientEncounter('patient-token', 'enc-1');
    }

    public function test_a_patient_with_no_linked_chart_never_reaches_prx(): void
    {
        $this->expectException(\RuntimeException::class);

        try {
            app(IssuePortalTokenAction::class)->execute(Patient::factory()->create());
        } finally {
            Http::assertNothingSent();
        }
    }
}
