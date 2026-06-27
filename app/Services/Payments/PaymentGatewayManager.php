<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Enums\Payments\GatewayProvider;
use App\Models\Payments\MerchantAccount;
use App\Services\Payments\Gateways\AuthorizeNetGateway;
use App\Services\Payments\Gateways\NmiGateway;
use Illuminate\Contracts\Container\Container;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    protected array $gateways = [
        GatewayProvider::Nmi->value => NmiGateway::class,
        GatewayProvider::AuthorizeNet->value => AuthorizeNetGateway::class,
    ];

    public function __construct(protected Container $container) {}

    /**
     * Resolve the gateway implementation for the given merchant account.
     */
    public function forAccount(MerchantAccount $account): PaymentGatewayInterface
    {
        return $this->driver($account->gateway_provider);
    }

    /**
     * Resolve the gateway implementation for the given merchant account ID.
     */
    public function forAccountId(string $merchantAccountId): PaymentGatewayInterface
    {
        $account = MerchantAccount::query()->findOrFail($merchantAccountId);

        return $this->forAccount($account);
    }

    public function driver(GatewayProvider $provider): PaymentGatewayInterface
    {
        $class = $this->gateways[$provider->value]
            ?? throw new \InvalidArgumentException("No gateway registered for provider [{$provider->value}].");

        return $this->container->make($class);
    }

    /**
     * Resolve the configured default merchant account and its gateway.
     * Throws if no default account is active.
     */
    public function default(): PaymentGatewayInterface
    {
        $account = MerchantAccount::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->forAccount($account);
    }
}
