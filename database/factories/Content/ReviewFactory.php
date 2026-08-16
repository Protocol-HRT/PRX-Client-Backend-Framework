<?php

namespace Database\Factories\Content;

use App\Models\Content\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'rating' => $this->faker->numberBetween(3, 5),
            'author_name' => $this->faker->firstName().' '.strtoupper($this->faker->randomLetter()).'.',
            'title' => $this->faker->optional()->sentence(4),
            'body' => $this->faker->paragraph(),
            'is_approved' => true,
            'source' => 'admin',
            'reviewed_at' => $this->faker->dateTimeBetween('-6 months'),
        ];
    }

    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => ['is_approved' => false]);
    }

    public function rating(int $rating): static
    {
        return $this->state(fn (array $attributes) => ['rating' => $rating]);
    }
}
