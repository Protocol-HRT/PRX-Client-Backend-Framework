<?php

namespace Database\Factories\Cms;

use App\Models\Cms\GlobalSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GlobalSection>
 */
class GlobalSectionFactory extends Factory
{
    protected $model = GlobalSection::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'cta-banner',
            'data' => [
                'heading' => fake()->sentence(4),
                'primary_cta_label' => 'Get started',
                'primary_cta_url' => '/start',
            ],
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }
}
