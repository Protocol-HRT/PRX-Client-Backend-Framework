<?php

namespace Database\Factories\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'subtitle' => fake()->sentence(6),
            'short_description' => fake()->paragraph(2),
            'description' => fake()->paragraphs(4, true),
            'status' => CatalogStatus::Published,
            'retail_price' => fake()->randomFloat(2, 99, 999),
            'sale_price' => null,
            'price_suffix' => '/mo',
            'provider_package_id' => null,
            'provider_package_sku' => null,
            'provider_encounter_type_id' => null,
            'badge_text' => null,
            'highlights' => null,
            'banner_image_path' => null,
            'is_featured' => false,
            'requires_lab' => false,
            'position' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'badge_text' => 'Most Popular',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CatalogStatus::Draft,
        ]);
    }
}
