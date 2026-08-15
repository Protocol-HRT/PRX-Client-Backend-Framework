<?php

namespace Database\Factories;

use App\Enums\SectionType;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageSection>
 */
class PageSectionFactory extends Factory
{
    protected $model = PageSection::class;

    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'type' => fake()->randomElement(SectionType::cases())->value,
            'position' => fake()->numberBetween(1, 10),
            'data' => [],
        ];
    }

    public function hero(): static
    {
        return $this->state([
            'type' => SectionType::Hero->value,
            'data' => [
                'headline' => 'Transform Your Health',
                'subheadline' => 'Physician-guided care.',
                'cta_label' => 'Get Started',
                'cta_url' => '/checkout',
            ],
        ]);
    }
}
