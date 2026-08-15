<?php

namespace Tests\Feature\ApiClients;

use App\Actions\ApiClients\IssueApiClientTokenAction;
use App\Models\ApiClient;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyApiClientOriginMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function makeToken(ApiClient $client, array $abilities = ['public:read']): string
    {
        return app(IssueApiClientTokenAction::class)->execute($client, 'test', $abilities);
    }

    public function test_request_without_token_passes_through(): void
    {
        $this->getJson('/api/v1/config')
            ->assertSuccessful();
    }

    public function test_active_client_with_no_origin_restriction_passes(): void
    {
        $client = ApiClient::factory()->create(['allowed_origins' => null]);
        $token = $this->makeToken($client);

        $this->withToken($token)
            ->withHeader('Origin', 'https://any-origin.com')
            ->getJson('/api/v1/config')
            ->assertSuccessful();
    }

    public function test_allowed_origin_passes(): void
    {
        $client = ApiClient::factory()
            ->withOrigins(['https://app.example.com'])
            ->create();
        $token = $this->makeToken($client);

        $this->withToken($token)
            ->withHeader('Origin', 'https://app.example.com')
            ->getJson('/api/v1/config')
            ->assertSuccessful();
    }

    public function test_disallowed_origin_is_rejected(): void
    {
        $client = ApiClient::factory()
            ->withOrigins(['https://app.example.com'])
            ->create();
        $token = $this->makeToken($client);

        $this->withToken($token)
            ->withHeader('Origin', 'https://evil.com')
            ->getJson('/api/v1/config')
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Origin not allowed.']);
    }

    public function test_missing_origin_header_is_rejected_when_origins_restricted(): void
    {
        $client = ApiClient::factory()
            ->withOrigins(['https://app.example.com'])
            ->create();
        $token = $this->makeToken($client);

        $this->withToken($token)
            ->getJson('/api/v1/config')
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Origin not allowed.']);
    }

    public function test_inactive_client_token_is_rejected(): void
    {
        $client = ApiClient::factory()->inactive()->create();
        // Bypass IssueApiClientTokenAction guard by creating token directly
        $token = $client->createToken('direct')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/config')
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'API client is inactive.']);
    }

    public function test_patient_token_bypasses_origin_check(): void
    {
        // Patient tokens belong to App\Models\Patient, not ApiClient — middleware must skip them
        $patient = Patient::factory()->create();
        $patientToken = $patient->createToken('portal')->plainTextToken;

        $this->withToken($patientToken)
            ->withHeader('Origin', 'https://unexpected-origin.com')
            ->getJson('/api/v1/config')
            ->assertSuccessful();
    }
}
