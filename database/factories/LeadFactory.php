<?php

namespace Database\Factories;

use App\Enums\CheckoutPath;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'status' => LeadStatus::New_,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-19 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female', 'other', 'prefer_not_to_say']),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => strtoupper(fake()->lexify('??')),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'sms_consent' => false,
            'email_consent' => false,
            'checkout_path' => CheckoutPath::PrescribeRx,
        ];
    }

    public function withConsents(): static
    {
        return $this->state([
            'sms_consent' => true,
            'email_consent' => true,
            'consent_given_at' => now(),
        ]);
    }
}
