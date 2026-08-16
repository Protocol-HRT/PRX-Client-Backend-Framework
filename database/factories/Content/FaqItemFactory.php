<?php

namespace Database\Factories\Content;

use App\Models\Content\FaqCategory;
use App\Models\Content\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqItem>
 */
class FaqItemFactory extends Factory
{
    protected $model = FaqItem::class;

    public function definition(): array
    {
        return [
            'faq_category_id' => null,
            'question' => rtrim($this->faker->sentence(), '.').'?',
            'answer' => $this->faker->paragraph(),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }

    public function inCategory(?FaqCategory $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'faq_category_id' => $category?->id ?? FaqCategory::factory(),
        ]);
    }
}
