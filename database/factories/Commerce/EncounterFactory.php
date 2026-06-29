<?php

namespace Database\Factories\Commerce;

use App\Enums\EncounterStatus;
use App\Models\Commerce\Encounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encounter>
 */
class EncounterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prescribe_rx_encounter_id' => fake()->uuid(),
            'prescribe_rx_patient_id' => fake()->uuid(),
            'status' => EncounterStatus::Submitted,
            'submitted_at' => now(),
            'total_amount' => fake()->randomFloat(2, 50, 500),
            'is_sandbox' => true,
        ];
    }
}
