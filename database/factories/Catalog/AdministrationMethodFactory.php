<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AdministrationMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdministrationMethod>
 */
class AdministrationMethodFactory extends Factory
{
    protected $model = AdministrationMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'abbreviation' => strtoupper(fake()->lexify('??')),
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
