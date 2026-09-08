<?php

namespace Tests\Feature\Api\V1\Patient;

use App\Models\Patient;
use App\Models\User;
use App\Services\PrescribeRx\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PatientAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // Anonymous registration must never resolve a clinical identity by email.
        $this->mock(Client::class, function ($mock) {
            $mock->shouldNotReceive('findPatientByEmail');
        });
    }

    // ── Register ─────────────────────────────────────────────────────────

    public function test_register_creates_patient_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/patient/auth/register', [
            'email' => 'jane@example.com',
            'password' => 'password123',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'patient' => ['uuid', 'email', 'first_name', 'last_name', 'has_prx_chart']],
            ]);

        $this->assertDatabaseHas('patients', ['email' => 'jane@example.com']);
    }

    public function test_register_does_not_link_or_verify_a_prx_chart_from_unproven_identity(): void
    {
        $response = $this->postJson('/api/v1/patient/auth/register', [
            'email' => 'linked@example.com',
            'password' => 'password123',
            'first_name' => 'Linked',
            'last_name' => 'Patient',
            'prx_patient_chart_id' => 'chart-uuid',
            'prx_patient_id' => 'user-uuid',
            'prx_chart_verified_at' => now()->toIso8601String(),
            'email_verified_at' => now()->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.patient.has_prx_chart', false);

        $this->assertDatabaseHas('patients', [
            'email' => 'linked@example.com',
            'prx_patient_chart_id' => null,
            'prx_patient_id' => null,
            'prx_chart_verified_at' => null,
            'email_verified_at' => null,
        ]);

        Http::assertNothingSent();
    }

    public function test_register_rejects_duplicate_email(): void
    {
        Patient::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/v1/patient/auth/register', [
            'email' => 'existing@example.com',
            'password' => 'password123',
            'first_name' => 'New',
            'last_name' => 'Patient',
        ])->assertUnprocessable();
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson('/api/v1/patient/auth/register', [])
            ->assertStatus(422);
    }

    // ── Login ─────────────────────────────────────────────────────────────

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $patient = Patient::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/patient/auth/login', [
            'email' => $patient->email,
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'patient' => ['uuid', 'email']],
            ]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $patient = Patient::factory()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/v1/patient/auth/login', [
            'email' => $patient->email,
            'password' => 'wrong',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson('/api/v1/patient/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'anything',
        ])->assertUnprocessable();
    }

    // ── Me ────────────────────────────────────────────────────────────────

    public function test_me_returns_patient_profile(): void
    {
        $patient = Patient::factory()->create();
        $token = $patient->createToken('test', ['patient:*'])->plainTextToken;

        $this->getJson('/api/v1/patient/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.email', $patient->email);
    }

    public function test_me_rejects_unauthenticated(): void
    {
        $this->getJson('/api/v1/patient/auth/me')->assertUnauthorized();
    }

    public function test_me_rejects_admin_user_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('admin-token', ['frontend:*'])->plainTextToken;

        $this->getJson('/api/v1/patient/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized();
    }

    // ── Logout ────────────────────────────────────────────────────────────

    public function test_logout_revokes_current_patient_token(): void
    {
        $patient = Patient::factory()->create();
        $token = $patient->createToken('test', ['patient:*'])->plainTextToken;

        $this->postJson('/api/v1/patient/auth/logout', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->assertSame(0, $patient->fresh()->tokens()->count());
    }
}
