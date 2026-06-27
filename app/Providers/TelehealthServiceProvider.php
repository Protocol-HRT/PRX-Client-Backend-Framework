<?php

namespace App\Providers;

use App\Contracts\Telehealth\TelehealthProviderInterface;
use App\Services\Telehealth\TelehealthManager;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the telehealth abstraction layer into the container.
 *
 * - `TelehealthManager` is a singleton so the resolved provider is cached
 *   for the lifetime of the request (avoids repeated settings lookups).
 *
 * - `TelehealthProviderInterface` resolves through the manager so callers
 *   that type-hint the interface get the active provider directly without
 *   having to go through the manager explicitly.
 */
class TelehealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelehealthManager::class);

        $this->app->bind(TelehealthProviderInterface::class, function ($app) {
            return $app->make(TelehealthManager::class)->provider();
        });
    }
}
