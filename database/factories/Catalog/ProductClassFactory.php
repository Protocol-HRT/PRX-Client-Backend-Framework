<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\ProductClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductClass>
 */
class ProductClassFactory extends Factory
{
    protected $model = ProductClass::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function mapped(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_product_class_id' => fake()->uuid(),
        ]);
    }
}
