<?php

namespace Database\Factories\Content;

use App\Enums\ProfileType;
use App\Models\Content\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'type' => ProfileType::Doctor,
            'name' => $name,
            'slug' => str($name)->slug()->value().'-'.fake()->unique()->numberBetween(1, 99999),
            'title' => 'Medical Director',
            'credentials' => 'MD',
            'is_published' => true,
        ];
    }
}
