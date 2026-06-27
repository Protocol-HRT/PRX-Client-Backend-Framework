<?php

namespace Database\Factories\Catalog;

use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\RebillStrategy;
use App\Models\Catalog\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'subtitle' => fake()->sentence(5),
            'short_description' => fake()->paragraph(1),
            'description' => fake()->paragraphs(2, true),
            'status' => CatalogStatus::Published,
            'billing_period' => BillingPeriod::Monthly,
            'term_months' => 1,
            'retail_price' => fake()->randomFloat(2, 49, 299),
            'sale_price' => null,
            'price_suffix' => '/mo',
            'provider_plan_id' => null,
            'provider_plan_sku' => null,
            'provider_product_ids' => null,
            'badge_text' => null,
            'is_featured' => false,
            'is_recurring' => true,
            'rebill_strategy' => RebillStrategy::AutoRenew,
            'trial_days' => null,
            'is_default' => false,
            'requires_lab' => false,
            'position' => 0,
        ];
    }

    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => BillingPeriod::OneTime,
            'term_months' => null,
            'is_recurring' => false,
            'rebill_strategy' => null,
        ]);
    }

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => BillingPeriod::Quarterly,
            'term_months' => 3,
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_period' => BillingPeriod::Annual,
            'term_months' => 12,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CatalogStatus::Draft,
        ]);
    }
}
