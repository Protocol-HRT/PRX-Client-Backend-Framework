<?php

namespace Database\Factories\Blog;

use App\Enums\PostStatus;
use App\Models\Blog\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6, true);

        return [
            'author_id' => null,
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 9999),
            'excerpt' => fake()->sentence(15),
            'content' => fake()->paragraphs(3, true),
            'hero_image_path' => null,
            'gallery' => null,
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(rand(1, 30)),
            'featured' => false,
            'read_time_minutes' => rand(3, 8),
            'meta_title' => null,
            'meta_description' => null,
            'og_image_path' => null,
            'position' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Archived,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Published,
            'published_at' => now()->addDays(rand(1, 30)),
        ]);
    }
}
