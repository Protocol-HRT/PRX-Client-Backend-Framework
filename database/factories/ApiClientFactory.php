<?php

namespace Database\Factories;

use App\Enums\TokenAbility;
use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Frontend',
            'description' => $this->faker->optional()->sentence(),
            'allowed_origins' => null,
            'default_abilities' => [TokenAbility::PublicRead->value],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withOrigins(array $origins): static
    {
        return $this->state(['allowed_origins' => $origins]);
    }

    public function checkout(): static
    {
        return $this->state([
            'default_abilities' => [
                TokenAbility::PublicRead->value,
                TokenAbility::Checkout->value,
            ],
        ]);
    }
}
