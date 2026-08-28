<?php

namespace App\Integrations;

use App\Enums\Integrations\IntegrationCapability;
use App\Integrations\Contracts\IntegrationDriver;
use App\Models\Integrations\IntegrationInstance;
use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * The catalogue of integrations this installation can be configured to use.
 *
 * ─── This is where vendors are named, and it is code ───────────────────
 *
 * A Klaviyo driver needs Klaviyo-specific code, so the vendor has to be named
 * somewhere; the design question was only WHICH layer. Not the workflow action
 * registry — a `push_to_klaviyo` case in a shipped enum means every install with
 * a different CRM forks the product to add one. Here, where a name is a
 * registration rather than a case in an enum, adding a vendor is additive and an
 * install can register its own from its own service provider.
 *
 * ─── Keys, never class names ───────────────────────────────────────────
 *
 * `integration_instances.provider` holds a key from this registry, and never a
 * class name. That is the security boundary, not a style preference: those rows
 * are operator-editable, and a class name in an editable row that is later
 * instantiated is arbitrary class instantiation in a product hundreds of
 * companies will deploy. The same rule governs `WorkflowRegistry`, for the same
 * reason, and it should govern the next registry too.
 *
 * An UNREGISTERED key resolves to nothing and throws when used. It does not fall
 * back to a default driver: a destination that quietly becomes a different
 * destination is worse than one that fails.
 *
 * ─── Capabilities are read off the class, not off the registration ─────
 *
 * `registerProvider()` takes no capability list. What a driver can do is
 * determined by which contracts it implements, so the two cannot drift — see
 * IntegrationCapability::capabilitiesOf(). What an OPERATOR has enabled is a
 * separate question, answered by the instance row.
 */
class IntegrationRegistry
{
    /**
     * @var array<string, array{
     *     driver: class-string<IntegrationDriver>,
     *     label: string,
     *     description: string,
     *     credentials: Closure|null,
     *     settings: Closure|null,
     * }>
     */
    private array $providers = [];

    /**
     * @param  class-string<IntegrationDriver>  $driver
     * @param  Closure|null  $credentials  Returns Filament components for the secret fields.
     *                                     A closure so the schema is built lazily and this
     *                                     registry stays usable outside the admin panel.
     * @param  Closure|null  $settings  Returns Filament components for non-secret config.
     */
    public function registerProvider(
        string $key,
        string $driver,
        string $label,
        string $description = '',
        ?Closure $credentials = null,
        ?Closure $settings = null,
    ): void {
        if (! is_a($driver, IntegrationDriver::class, true)) {
            // Loud at boot, where a developer sees it, rather than at run time in
            // an operator's funnel.
            throw new InvalidArgumentException(
                "Integration driver [{$driver}] must implement ".IntegrationDriver::class.'.'
            );
        }

        $this->providers[$key] = [
            'driver' => $driver,
            'label' => $label,
            'description' => $description,
            'credentials' => $credentials,
            'settings' => $settings,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function providers(): array
    {
        return $this->providers;
    }

    /** @return array<string, mixed>|null */
    public function provider(string $key): ?array
    {
        return $this->providers[$key] ?? null;
    }

    /** @return array<string, string> key => label, for a Filament select. */
    public function providerOptions(): array
    {
        return collect($this->providers)
            ->map(fn (array $definition): string => $definition['label'])
            ->all();
    }

    /**
     * What the code behind a provider key is capable of.
     *
     * @return list<IntegrationCapability>
     */
    public function capabilitiesFor(string $key): array
    {
        $driver = $this->providers[$key]['driver'] ?? null;

        return $driver === null ? [] : IntegrationCapability::capabilitiesOf($driver);
    }

    /**
     * Resolve the driver behind a configured instance.
     *
     * Throws rather than returning null. A caller reaching here is about to send
     * somebody's data somewhere, and "the destination could not be resolved" must
     * stop that, not become an empty result that looks like success.
     */
    public function driverFor(IntegrationInstance $instance): IntegrationDriver
    {
        $definition = $this->providers[$instance->provider] ?? null;

        if ($definition === null) {
            throw new RuntimeException(
                "Integration [{$instance->slug}] names provider [{$instance->provider}], which is not "
                .'registered on this installation.'
            );
        }

        return app($definition['driver']);
    }

    /**
     * Whether an instance can AND may do something.
     *
     * Both halves, always. The operator's toggle without the interface check
     * would let a stale row promise a capability the driver has since lost; the
     * interface check without the toggle would ignore the operator's own
     * authorisation. Callers get one question to ask instead of two to remember.
     */
    public function instanceOffers(IntegrationInstance $instance, IntegrationCapability $capability): bool
    {
        if (! $instance->offers($capability->value)) {
            return false;
        }

        $driver = $this->providers[$instance->provider]['driver'] ?? null;

        return $driver !== null && is_a($driver, $capability->contract(), true);
    }

    /**
     * Active instances that can and may do something.
     *
     * The action palette's question, and the reason the whole registry exists in
     * front of a database table. Filtering happens in PHP because the
     * interface half of the answer lives in code, not in the row.
     *
     * @return Collection<int, IntegrationInstance>
     */
    public function instancesOffering(IntegrationCapability $capability)
    {
        return IntegrationInstance::query()
            ->active()
            ->offering($capability->value)
            ->orderBy('name')
            ->get()
            ->filter(fn (IntegrationInstance $instance): bool => $this->instanceOffers($instance, $capability))
            ->values();
    }
}
