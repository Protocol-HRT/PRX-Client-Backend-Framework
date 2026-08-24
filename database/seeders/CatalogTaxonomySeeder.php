<?php

namespace Database\Seeders;

use App\Models\Catalog\Category;
use App\Models\Catalog\Tag;
use Illuminate\Database\Seeder;

/**
 * Seeds the canonical category + tag taxonomy for the catalog module.
 * Idempotent — uses updateOrCreate so re-running won't duplicate.
 */
class CatalogTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'HIM', 'slug' => 'him', 'position' => 10, 'description' => 'Treatments and protocols for men.'],
            ['name' => 'HER', 'slug' => 'her', 'position' => 20, 'description' => 'Treatments and protocols for women.'],
            ['name' => 'HRT', 'slug' => 'hrt', 'position' => 30, 'description' => 'Hormone replacement therapy.'],
            ['name' => 'GLP-1', 'slug' => 'glp-1', 'position' => 40, 'description' => 'GLP-1 receptor agonists for weight management.'],
            ['name' => 'Peptides', 'slug' => 'peptides', 'position' => 50, 'description' => 'Therapeutic peptides.'],
            ['name' => 'Longevity', 'slug' => 'longevity', 'position' => 60, 'description' => 'Healthspan and longevity protocols.'],
        ];

        foreach ($categories as $row) {
            Category::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['is_visible' => true],
            );
        }

        $tags = [
            ['name' => 'Most Popular', 'slug' => 'most-popular', 'color' => 'rose', 'position' => 10],
            ['name' => 'Featured', 'slug' => 'featured', 'color' => 'amber', 'position' => 20],
            ['name' => 'Lab Required', 'slug' => 'lab-required', 'color' => 'sky', 'position' => 30],
            ['name' => 'New', 'slug' => 'new', 'color' => 'emerald', 'position' => 40],
            ['name' => 'Best Value', 'slug' => 'best-value', 'color' => 'violet', 'position' => 50],
        ];

        foreach ($tags as $row) {
            Tag::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['is_visible' => true],
            );
        }
    }
}
