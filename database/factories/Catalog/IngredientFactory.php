<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = strtoupper(fake()->unique()->lexify('???-###'));

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
            'provider_ingredient_id' => fake()->uuid(),
        ]);
    }
}
