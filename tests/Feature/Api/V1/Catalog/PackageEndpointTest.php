<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_packages_index_returns_published_packages_only(): void
    {
        Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Visible']);
        Package::factory()->create(['status' => CatalogStatus::Draft, 'name' => 'Hidden']);

        $this->getJson('/api/v1/catalog/packages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible');
    }

    public function test_packages_index_includes_published_plans(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Draft]);

        $this->getJson('/api/v1/catalog/packages')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.plans');
    }

    public function test_packages_index_response_shape(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'retail_price' => 199.00]);

        $this->getJson('/api/v1/catalog/packages')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'name', 'slug', 'badge_text', 'highlights',
                    'is_featured', 'requires_lab', 'price_range', 'provider',
                    'plans', 'categories', 'tags',
                ]],
            ]);
    }

    public function test_package_show_returns_single_published_package(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $package->slug);
    }

    public function test_package_show_includes_products(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        $package->products()->attach($product->id, ['sort_order' => 1, 'is_included' => true]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.name', $product->name);
    }

    public function test_package_show_computes_price_range_from_plans(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'sale_price' => 99.00]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'sale_price' => 249.00]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.price_range.from', '99.00')
            ->assertJsonPath('data.price_range.to', '249.00')
            ->assertJsonPath('data.price_range.currency', 'USD');
    }

    public function test_package_show_returns_404_for_draft(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Draft]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")->assertNotFound();
    }

    public function test_plan_billing_section_contains_subscription_fields(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        Plan::factory()->create([
            'package_id' => $package->id,
            'status' => CatalogStatus::Published,
            'is_recurring' => true,
            'term_months' => 3,
            'rebill_strategy' => 'auto_renew',
        ]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.plans.0.billing.is_recurring', true)
            ->assertJsonPath('data.plans.0.billing.term_months', 3)
            ->assertJsonPath('data.plans.0.billing.rebill_strategy', 'auto_renew');
    }
}
