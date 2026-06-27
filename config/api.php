<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Config Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long the GET /api/v1/config response is cached. The Settings
    | observer clears this cache key whenever an admin saves a settings page,
    | so the TTL is effectively just a safety net.
    |
    */
    'config_ttl' => env('API_CONFIG_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Default API Token Abilities
    |--------------------------------------------------------------------------
    |
    | Ability sets granted per token type. These are the scopes that callers
    | can request when authenticating. Abilities are checked with
    | $user->tokenCan('frontend:*') on protected routes.
    |
    */
    'token_abilities' => [
        'frontend' => ['frontend:*'],
        'patient' => ['patient:read', 'patient:write', 'order:read', 'prescription:read', 'subscription:read'],
        'integration' => ['integration:*'],
        'admin' => ['admin:*'],
    ],
];
