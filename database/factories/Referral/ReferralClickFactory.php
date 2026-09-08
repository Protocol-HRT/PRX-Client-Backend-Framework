<?php

namespace Database\Factories\Referral;

use App\Models\Referral\ReferralClick;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReferralClick> */
class ReferralClickFactory extends Factory
{
    protected $model = ReferralClick::class;

    public function definition(): array
    {
        return [
            'code' => Str::lower(Str::random(8)),
            'visitor_id' => (string) Str::uuid(),
            'ip_address' => $this->faker->ipv4(),
            'landing_url' => 'https://example.test/?ref=abc',
            'clicked_at' => now(),
        ];
    }
}
