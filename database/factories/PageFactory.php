<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => ucwords($title),
            'slug' => Str::slug($title),
            'status' => PageStatus::Published,
            'meta_title' => ucwords($title).' | Site',
            'meta_description' => fake()->sentence(),
            'noindex' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PageStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(['status' => PageStatus::Archived]);
    }
}
