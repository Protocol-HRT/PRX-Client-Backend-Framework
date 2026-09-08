<?php

namespace Database\Factories\Referral;

use App\Models\Referral\ReferralSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ReferralSource> */
class ReferralSourceFactory extends Factory
{
    protected $model = ReferralSource::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'type' => 'affiliate',
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'code_prefix' => Str::upper(Str::random(4)),
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->unique()->safeEmail(),
            'commission_rate' => 10.00,
            'portal_enabled' => true,
            'is_active' => true,
        ];
    }

    public function salesGroup(): static
    {
        return $this->state(fn () => ['type' => 'sales_group']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
