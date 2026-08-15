<?php

namespace Tests\Feature\ApiClients;

use App\Actions\ApiClients\IssueApiClientTokenAction;
use App\Enums\TokenAbility;
use App\Models\ApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ApiClientTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_action_returns_plain_text_token(): void
    {
        $client = ApiClient::factory()->create();
        $action = new IssueApiClientTokenAction;

        $token = $action->execute($client, 'test-token');

        $this->assertNotEmpty($token);
        $this->assertStringContainsString('|', $token); // Sanctum plain-text format: {id}|{hash}
        $this->assertCount(1, $client->tokens);
    }

    public function test_issue_action_stores_specified_abilities(): void
    {
        $client = ApiClient::factory()->create();
        $action = new IssueApiClientTokenAction;

        $action->execute($client, 'checkout-token', [TokenAbility::Checkout->value]);

        $tokenRecord = $client->tokens()->first();
        $this->assertContains(TokenAbility::Checkout->value, $tokenRecord->abilities);
    }

    public function test_issue_action_falls_back_to_client_default_abilities(): void
    {
        $client = ApiClient::factory()->create([
            'default_abilities' => [TokenAbility::PublicRead->value, TokenAbility::Checkout->value],
        ]);
        $action = new IssueApiClientTokenAction;

        $action->execute($client, 'default-token');

        $tokenRecord = $client->tokens()->first();
        $this->assertContains(TokenAbility::PublicRead->value, $tokenRecord->abilities);
        $this->assertContains(TokenAbility::Checkout->value, $tokenRecord->abilities);
    }

    public function test_issue_action_throws_for_inactive_client(): void
    {
        $client = ApiClient::factory()->inactive()->create();
        $action = new IssueApiClientTokenAction;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inactive');

        $action->execute($client, 'should-fail');
    }

    public function test_multiple_tokens_can_be_issued_for_same_client(): void
    {
        $client = ApiClient::factory()->create();
        $action = new IssueApiClientTokenAction;

        $action->execute($client, 'token-one');
        $action->execute($client, 'token-two');
        $action->execute($client, 'token-three');

        $this->assertCount(3, $client->fresh()->tokens);
    }
}
