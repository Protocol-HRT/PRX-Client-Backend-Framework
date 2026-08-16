<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductCoa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCoa>
 */
class ProductCoaFactory extends Factory
{
    protected $model = ProductCoa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_number' => strtoupper(fake()->unique()->bothify('BATCH-####??')),
            'file_path' => 'catalog/coas/'.fake()->uuid().'.pdf',
            'file_type' => 'pdf',
            'issued_at' => fake()->dateTimeBetween('-1 year'),
            'is_visible' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => 'catalog/coas/'.fake()->uuid().'.jpg',
            'file_type' => 'jpg',
        ]);
    }
}
