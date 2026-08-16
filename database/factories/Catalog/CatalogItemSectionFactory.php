<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\CatalogItemSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItemSection>
 */
class CatalogItemSectionFactory extends Factory
{
    protected $model = CatalogItemSection::class;

    public function definition(): array
    {
        return [
            'type' => 'text-block',
            'data' => ['heading' => $this->faker->sentence(3), 'body' => $this->faker->paragraph()],
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function type(string $type, array $data = []): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type, 'data' => $data]);
    }
}
