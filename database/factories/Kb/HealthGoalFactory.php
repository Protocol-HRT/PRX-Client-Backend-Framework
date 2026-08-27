<?php

namespace Database\Factories\Kb;

use App\Models\Kb\HealthGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthGoal>
 */
class HealthGoalFactory extends Factory
{
    protected $model = HealthGoal::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'prompt' => $name.' — the outcome, in their words',
            'is_active' => true,
            'show_in_quiz' => true,
        ];
    }

    /** Live for everything mapped to it, but withdrawn from intake. */
    public function hiddenFromQuiz(): static
    {
        return $this->state(fn (): array => ['show_in_quiz' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
