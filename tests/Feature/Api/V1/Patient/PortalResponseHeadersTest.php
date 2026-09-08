<?php

namespace Tests\Feature\Api\V1\Patient;

use App\Models\Patient;
use App\Services\PrescribeRx\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every patient-portal proxy response is one patient's PHI, so none of it may
 * be written to a cache — not a CDN's, not the browser's on a shared device.
 *
 * These assert the HEADERS rather than the body, because that is the property
 * that has to survive refactoring: a header set inside a controller action is
 * lost the moment a handler returns early, and the responses that return early
 * (401, 403, validation failures) are the ones that echo ids back.
 */
class PortalResponseHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function actingAsPatient(): Patient
    {
        $patient = Patient::factory()->withPrxChart()->create();
        Sanctum::actingAs($patient, ['*']);

        return $patient;
    }

    public function test_a_successful_portal_response_may_never_be_stored(): void
    {
        $this->actingAsPatient();

        $this->mock(Client::class, function ($mock): void {
            $mock->shouldReceive('issuePatientToken')->andReturn([
                'token' => 'patient-token',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ]);
            $mock->shouldReceive('getPatientDashboard')->andReturn(['encounters' => []]);
        });

        $response = $this->getJson('/api/v1/patient/dashboard');

        $response->assertOk();

        // no-store, not merely no-cache: no-cache still permits a copy on disk.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function test_an_unauthenticated_rejection_is_also_never_stored(): void
    {
        // The early-return path. A cached 401 is harmless; a header contract
        // that silently stops applying on early returns is not, because the
        // next handler added below it inherits the gap.
        $response = $this->getJson('/api/v1/patient/dashboard');

        $response->assertUnauthorized();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_every_portal_route_carries_the_no_store_middleware(): void
    {
        // Asserted over the route table, not one request: the failure mode is a
        // route added later OUTSIDE the group, which no per-endpoint test catches.
        $portalRoutes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains((string) $route->getName(), 'patient.portal.'));

        $this->assertGreaterThan(0, $portalRoutes->count(), 'No portal routes found — did the name prefix change?');

        foreach ($portalRoutes as $route) {
            $this->assertContains(
                'no-store',
                $route->gatherMiddleware(),
                "Portal route [{$route->getName()}] does not carry no-store."
            );
            $this->assertContains(
                'patient',
                $route->gatherMiddleware(),
                "Portal route [{$route->getName()}] is not gated to a patient session."
            );
        }
    }
}
