<?php

namespace Database\Factories\Payments;

use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use App\Models\Payments\MerchantAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MerchantAccountFactory extends Factory
{
    protected $model = MerchantAccount::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->company().' Merchant',
            'gateway_provider' => GatewayProvider::AuthorizeNet,
            'environment' => GatewayEnvironment::Sandbox,
            'authnet_api_login_id' => $this->faker->bothify('??########'),
            'authnet_transaction_key' => $this->faker->sha256(),
            'authnet_public_client_key' => null,
            'is_active' => true,
            'is_default' => false,
            'transaction_weight' => 1,
            'allows_recurring_payments' => false,
        ];
    }

    public function nmi(): static
    {
        return $this->state([
            'gateway_provider' => GatewayProvider::Nmi,
            'nmi_security_key' => $this->faker->sha256(),
            'authnet_api_login_id' => null,
            'authnet_transaction_key' => null,
        ]);
    }

    public function production(): static
    {
        return $this->state(['environment' => GatewayEnvironment::Production]);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
