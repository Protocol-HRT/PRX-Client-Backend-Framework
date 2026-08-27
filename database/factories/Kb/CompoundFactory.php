<?php

namespace Database\Factories\Kb;

use App\Enums\Kb\RegulatoryStatus;
use App\Models\Content\Profile;
use App\Models\Kb\Compound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compound>
 */
class CompoundFactory extends Factory
{
    protected $model = Compound::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => str($name)->slug()->value(),
            'tagline' => fake()->sentence(),
            'is_peptide' => true,
            'regulatory_status' => RegulatoryStatus::ResearchOnly,
            'description' => '<p>'.fake()->paragraph().'</p>',
            'overview' => '<h2>Overview</h2><p>'.fake()->paragraph().'</p>',
            'dosing_guidelines' => '<table><tbody><tr><td>1-4</td><td>0.25 mg</td></tr></tbody></table>',
            'is_published' => false,
        ];
    }

    /**
     * Published with a regulatory status — the combination the public API
     * serves, and deliberately WITHOUT a reviewer, because one is not required.
     * A fixture that always attached a clinician would teach the next reader
     * the opposite of the rule.
     */
    public function live(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
        ]);
    }

    /** Adds the optional clinician byline, for the tests that render it. */
    public function reviewed(): static
    {
        return $this->state(fn (): array => [
            'reviewed_at' => now(),
            'reviewed_by_profile_id' => Profile::factory(),
        ]);
    }

    public function notPeptide(): static
    {
        return $this->state(fn (): array => ['is_peptide' => false]);
    }
}
