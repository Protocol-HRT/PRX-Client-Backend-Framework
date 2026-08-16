<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\MeasurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeasurementUnit>
 */
class MeasurementUnitFactory extends Factory
{
    protected $model = MeasurementUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $abbreviation = fake()->unique()->lexify('??');

        return [
            'name' => ucfirst(fake()->word()),
            'abbreviation' => $abbreviation,
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
}
