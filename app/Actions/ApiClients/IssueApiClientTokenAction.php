<?php

namespace App\Actions\ApiClients;

use App\Enums\TokenAbility;
use App\Models\ApiClient;
use RuntimeException;

class IssueApiClientTokenAction
{
    /**
     * Issue a Sanctum token for the given API client.
     *
     * Returns the plain-text token — this is the only time it is available.
     *
     * @param  list<string>  $abilities  Overrides the client's default_abilities when provided.
     */
    public function execute(ApiClient $client, string $name, array $abilities = []): string
    {
        if (! $client->is_active) {
            throw new RuntimeException('Cannot issue a token for an inactive API client.');
        }

        $resolvedAbilities = $abilities ?: $client->default_abilities ?: [TokenAbility::PublicRead->value];

        return $client->createToken($name, $resolvedAbilities)->plainTextToken;
    }
}
