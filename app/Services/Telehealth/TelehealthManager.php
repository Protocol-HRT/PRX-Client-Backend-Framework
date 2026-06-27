<?php

namespace App\Services\Telehealth;

use App\Contracts\Telehealth\TelehealthProviderInterface;
use App\Settings\IntegrationSettings;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves and caches the active telehealth provider based on tenant settings.
 *
 * Mirrors the pattern used by Laravel's `AuthManager` and `CacheManager`:
 * a thin manager that holds a resolved driver instance and forwards all
 * method calls to it via `__call()`.
 *
 * Resolution order:
 *   1. If `IntegrationSettings::$prescribe_rx_enabled` is true → PrescribeRxProvider
 *   2. Otherwise → NullTelehealthProvider (safe no-op for fresh installs)
 *
 * Adding a second provider later:
 *   - Add a new `$settings->some_other_provider_enabled` check here.
 *   - Implement `TelehealthProviderInterface` in a new provider class.
 *   - No callers change — they inject `TelehealthManager` or `TelehealthProviderInterface`.
 */
class TelehealthManager
{
    /** @var TelehealthProviderInterface|null Lazily resolved and cached per-request. */
    protected ?TelehealthProviderInterface $resolved = null;

    public function __construct(
        protected readonly Container $container,
        protected readonly IntegrationSettings $settings,
    ) {}

    /**
     * Resolve the active provider, or a specific provider by slug.
     *
     * @param  string|null  $name  Provider slug (e.g. "prescribe-rx"). When null,
     *                             the active provider is determined from settings.
     */
    public function provider(?string $name = null): TelehealthProviderInterface
    {
        if ($name !== null) {
            return $this->resolveByName($name);
        }

        if ($this->resolved === null) {
            $this->resolved = $this->resolveFromSettings();
        }

        return $this->resolved;
    }

    /**
     * Forward any method call to the resolved default provider.
     * Allows `app(TelehealthManager::class)->submitIntake(...)` as a
     * convenient shorthand without having to call `->provider()` first.
     *
     * @param  array<int, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->provider()->$method(...$args);
    }

    // ─── Internals ────────────────────────────────────────────────────────

    protected function resolveFromSettings(): TelehealthProviderInterface
    {
        if ($this->settings->prescribe_rx_enabled) {
            return $this->container->make(PrescribeRxProvider::class);
        }

        return $this->container->make(NullTelehealthProvider::class);
    }

    protected function resolveByName(string $name): TelehealthProviderInterface
    {
        return match ($name) {
            'prescribe-rx' => $this->container->make(PrescribeRxProvider::class),
            'null' => $this->container->make(NullTelehealthProvider::class),
            default => throw new \InvalidArgumentException(
                "Unknown telehealth provider [{$name}]. Known providers: prescribe-rx, null."
            ),
        };
    }
}
