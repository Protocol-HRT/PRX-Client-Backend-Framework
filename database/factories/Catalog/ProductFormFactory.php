<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\ProductForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductForm>
 */
class ProductFormFactory extends Factory
{
    protected $model = ProductForm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'requires_volume' => false,
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

    public function volumetric(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_volume' => true,
        ]);
    }
}
