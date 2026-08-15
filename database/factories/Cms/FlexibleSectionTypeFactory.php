<?php

namespace Database\Factories\Cms;

use App\Models\Cms\FlexibleSectionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FlexibleSectionType>
 */
class FlexibleSectionTypeFactory extends Factory
{
    protected $model = FlexibleSectionType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->bs());

        return [
            'name' => Str::limit($name, 120, ''),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'schema' => [
                'fields' => [
                    ['key' => 'heading', 'kind' => 'text', 'label' => 'Heading'],
                ],
            ],
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }
}
