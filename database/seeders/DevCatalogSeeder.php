<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Enums\CatalogStatus;
use App\Enums\RebillStrategy;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Catalog\Tag;
use Illuminate\Database\Seeder;

/**
 * Seeds a small, brand-neutral development catalog: generic compound products,
 * two packages with first-month / recurring plan structures, wired to the
 * canonical taxonomy. No client branding, no images, no provider IDs —
 * provider mapping is set per install once prescribe-rx sync is configured.
 *
 * NOT part of DatabaseSeeder: run explicitly with
 *   php artisan db:seed --class=DevCatalogSeeder
 *
 * Idempotent — keyed on slug via updateOrCreate.
 */
class DevCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogTaxonomySeeder::class);

        $products = [
            [
                'slug' => 'semaglutide',
                'name' => 'Semaglutide',
                'subtitle' => 'Weekly GLP-1 injection for weight management',
                'short_description' => 'A physician-supervised GLP-1 receptor agonist protocol supporting appetite regulation and sustainable weight loss.',
                'description' => 'Semaglutide is a GLP-1 receptor agonist prescribed for weight management under clinical supervision. Your program includes an initial provider consultation, personalized dose titration, and ongoing check-ins.',
                'retail_price' => 299,
                'price_suffix' => '/mo',
                'is_featured' => true,
                'badge_text' => 'Most Popular',
                'requires_lab' => true,
                'position' => 10,
                'categories' => ['glp-1'],
                'tags' => ['most-popular', 'lab-required'],
                'highlights' => ['Weekly self-administered injection', 'Physician-monitored dose titration', 'Ongoing provider check-ins'],
            ],
            [
                'slug' => 'tirzepatide',
                'name' => 'Tirzepatide',
                'subtitle' => 'Dual GIP/GLP-1 therapy for metabolic health',
                'short_description' => 'A dual-action incretin therapy for weight management and metabolic support, prescribed and monitored by licensed providers.',
                'description' => 'Tirzepatide combines GIP and GLP-1 receptor activity in a single weekly injection. Includes provider consultation, titration schedule, and follow-up care.',
                'retail_price' => 399,
                'price_suffix' => '/mo',
                'is_featured' => true,
                'requires_lab' => true,
                'position' => 20,
                'categories' => ['glp-1'],
                'tags' => ['featured', 'lab-required'],
                'highlights' => ['Dual-pathway metabolic support', 'Weekly injection', 'Provider-guided titration'],
            ],
            [
                'slug' => 'bpc-157',
                'name' => 'BPC-157',
                'subtitle' => 'Peptide support for recovery and repair',
                'short_description' => 'A recovery-focused peptide protocol used to support tissue repair and gut health, available after provider review.',
                'description' => 'BPC-157 is a synthetic peptide studied for its role in tissue repair and recovery. Your protocol is reviewed and approved by a licensed provider before fulfillment.',
                'retail_price' => 179,
                'price_suffix' => '/mo',
                'position' => 30,
                'categories' => ['peptides'],
                'tags' => ['new'],
                'highlights' => ['Recovery and repair support', 'Subcutaneous or oral formats', 'Provider-reviewed protocol'],
            ],
            [
                'slug' => 'nad-plus',
                'name' => 'NAD+',
                'subtitle' => 'Cellular energy and longevity support',
                'short_description' => 'NAD+ restoration therapy supporting cellular energy metabolism and healthy aging.',
                'description' => 'NAD+ levels decline with age. This protocol supports cellular energy production and is commonly paired with recovery or longevity stacks.',
                'retail_price' => 249,
                'price_suffix' => '/mo',
                'position' => 40,
                'categories' => ['longevity'],
                'tags' => [],
                'highlights' => ['Cellular energy support', 'Flexible dosing formats', 'Pairs with recovery protocols'],
            ],
            [
                'slug' => 'sermorelin',
                'name' => 'Sermorelin',
                'subtitle' => 'Growth-hormone-releasing peptide therapy',
                'short_description' => 'A GHRH analog protocol supporting natural growth hormone production, sleep quality, and recovery.',
                'description' => "Sermorelin stimulates the body's own growth hormone production. Prescribed after provider evaluation, with periodic follow-up to adjust dosing.",
                'retail_price' => 199,
                'price_suffix' => '/mo',
                'position' => 50,
                'categories' => ['peptides', 'longevity'],
                'tags' => [],
                'highlights' => ['Supports natural GH production', 'Evening dosing protocol', 'Recovery and sleep support'],
            ],
            [
                'slug' => 'testosterone-cypionate',
                'name' => 'Testosterone Cypionate',
                'subtitle' => 'Physician-managed testosterone replacement',
                'short_description' => 'Comprehensive TRT with baseline labs, provider consultation, and ongoing hormone monitoring.',
                'description' => 'A complete testosterone replacement program: baseline lab work, provider consultation, personalized dosing, and scheduled follow-up labs to keep levels optimized safely.',
                'retail_price' => 149,
                'price_suffix' => '/mo',
                'requires_lab' => true,
                'position' => 60,
                'categories' => ['hrt', 'him'],
                'tags' => ['lab-required'],
                'highlights' => ['Baseline and follow-up labs included', 'Licensed provider oversight', 'Injectable or topical formats'],
            ],
        ];

        $productModels = [];

        foreach ($products as $row) {
            $categories = Category::whereIn('slug', $row['categories'])->pluck('id');
            $tags = Tag::whereIn('slug', $row['tags'])->pluck('id');
            $highlights = array_map(fn (string $item): array => ['item' => $item], $row['highlights']);

            $product = Product::updateOrCreate(
                ['slug' => $row['slug']],
                collect($row)->except(['slug', 'categories', 'tags', 'highlights'])->all() + [
                    'status' => CatalogStatus::Published,
                    'highlights' => $highlights,
                    'is_in_stock' => true,
                ],
            );

            $product->categories()->sync($categories);
            $product->tags()->sync($tags);

            $productModels[$row['slug']] = $product;
        }

        $packages = [
            [
                'slug' => 'recovery-stack',
                'name' => 'Recovery Stack',
                'subtitle' => 'BPC-157 and NAD+ for repair and resilience',
                'short_description' => 'A combined peptide protocol supporting tissue repair, energy, and recovery.',
                'description' => 'The Recovery Stack pairs BPC-157 with NAD+ for a comprehensive repair-and-recover protocol, reviewed and approved by a licensed provider.',
                'position' => 10,
                'categories' => ['peptides', 'longevity'],
                'tags' => ['best-value'],
                'products' => ['bpc-157', 'nad-plus'],
                'highlights' => ['Two synergistic protocols', 'Single provider review', 'Save versus individual pricing'],
                'plans' => [
                    ['slug' => 'recovery-stack-monthly', 'name' => 'Monthly', 'retail_price' => 379, 'sale_price' => 329, 'billing_period' => BillingPeriod::Monthly, 'term_months' => 1, 'is_recurring' => true, 'is_default' => true, 'badge_text' => null],
                    ['slug' => 'recovery-stack-quarterly', 'name' => '3-Month', 'retail_price' => 349, 'sale_price' => null, 'billing_period' => BillingPeriod::Monthly, 'term_months' => 3, 'is_recurring' => true, 'is_default' => false, 'badge_text' => 'Best Value'],
                    ['slug' => 'recovery-stack-one-time', 'name' => 'One-Time', 'retail_price' => 429, 'sale_price' => null, 'billing_period' => BillingPeriod::OneTime, 'term_months' => 1, 'is_recurring' => false, 'is_default' => false, 'badge_text' => null],
                ],
            ],
            [
                'slug' => 'metabolic-reset',
                'name' => 'Metabolic Reset',
                'subtitle' => 'GLP-1 therapy plus cellular energy support',
                'short_description' => 'Semaglutide paired with NAD+ for weight management with energy support.',
                'description' => 'The Metabolic Reset combines physician-supervised Semaglutide with NAD+ support — designed for sustainable weight loss without the energy crash.',
                'is_featured' => true,
                'requires_lab' => true,
                'position' => 20,
                'categories' => ['glp-1', 'longevity'],
                'tags' => ['most-popular', 'lab-required'],
                'products' => ['semaglutide', 'nad-plus'],
                'highlights' => ['Physician-supervised GLP-1 program', 'NAD+ energy support included', 'Labs and follow-ups included'],
                'plans' => [
                    ['slug' => 'metabolic-reset-monthly', 'name' => 'Monthly', 'retail_price' => 499, 'sale_price' => 399, 'billing_period' => BillingPeriod::Monthly, 'term_months' => 1, 'is_recurring' => true, 'is_default' => true, 'badge_text' => 'First Month Offer'],
                    ['slug' => 'metabolic-reset-biannual', 'name' => '6-Month', 'retail_price' => 449, 'sale_price' => null, 'billing_period' => BillingPeriod::Monthly, 'term_months' => 6, 'is_recurring' => true, 'is_default' => false, 'badge_text' => 'Best Value'],
                ],
            ],
        ];

        foreach ($packages as $row) {
            $categories = Category::whereIn('slug', $row['categories'])->pluck('id');
            $tags = Tag::whereIn('slug', $row['tags'])->pluck('id');
            $highlights = array_map(fn (string $item): array => ['item' => $item], $row['highlights']);

            $package = Package::updateOrCreate(
                ['slug' => $row['slug']],
                collect($row)->except(['slug', 'categories', 'tags', 'products', 'highlights', 'plans'])->all() + [
                    'status' => CatalogStatus::Published,
                    'highlights' => $highlights,
                    'is_in_stock' => true,
                    'price_suffix' => '/mo',
                ],
            );

            $package->categories()->sync($categories);
            $package->tags()->sync($tags);
            $package->products()->sync(
                collect($row['products'])->map(fn (string $slug) => $productModels[$slug]->id)->all()
            );

            foreach ($row['plans'] as $i => $plan) {
                Plan::updateOrCreate(
                    ['slug' => $plan['slug']],
                    $plan + [
                        'package_id' => $package->id,
                        'status' => CatalogStatus::Published,
                        'price_suffix' => $plan['is_recurring'] ? '/mo' : null,
                        'rebill_strategy' => $plan['is_recurring'] ? RebillStrategy::AutoRenew : null,
                        'position' => ($i + 1) * 10,
                    ],
                );
            }
        }
    }
}
