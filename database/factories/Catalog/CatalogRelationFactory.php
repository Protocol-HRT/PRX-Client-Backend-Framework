<?php

namespace Database\Factories\Catalog;

use App\Enums\CatalogRelationType;
use App\Models\Catalog\CatalogRelation;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogRelation>
 */
class CatalogRelationFactory extends Factory
{
    protected $model = CatalogRelation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_type' => Product::class,
            'source_id' => Product::factory(),
            'related_type' => Product::class,
            'related_id' => Product::factory(),
            'relation_type' => CatalogRelationType::Related,
            'position' => 0,
        ];
    }

    public function pairsWith(): static
    {
        return $this->state(fn (array $attributes) => [
            'relation_type' => CatalogRelationType::PairsWith,
        ]);
    }
}
