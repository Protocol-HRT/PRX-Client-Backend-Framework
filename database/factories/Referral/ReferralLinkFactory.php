<?php

namespace Database\Factories\Referral;

use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReferralLink> */
class ReferralLinkFactory extends Factory
{
    protected $model = ReferralLink::class;

    public function definition(): array
    {
        return [
            'referral_source_id' => ReferralSource::factory(),
            'code' => Str::lower(Str::random(8)),
            'campaign_name' => $this->faker->words(2, true),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
