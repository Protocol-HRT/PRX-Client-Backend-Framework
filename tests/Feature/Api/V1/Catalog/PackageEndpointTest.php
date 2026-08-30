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
        // The package's own price is explicit and sits INSIDE the plan span, so
        // the range is the plans'. It used to be whatever the factory rolled,
        // which passed only because the resource ignored it entirely.
        $package = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'retail_price' => 199.00,
            'sale_price' => null,
        ]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'sale_price' => 99.00]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'sale_price' => 249.00]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.price_range.from', 99)
            ->assertJsonPath('data.price_range.to', 249)
            ->assertJsonPath('data.price_range.currency', 'USD');
    }

    public function test_a_package_on_sale_below_its_cheapest_plan_moves_the_range(): void
    {
        // THE CASE THE OPERATOR ASKED ABOUT. Plans are normally the cheaper way
        // to buy, so a range read from plans alone looks right almost always —
        // and is wrong exactly when a single purchase is discounted beneath
        // them, which is what putting a package on sale is for. A range that
        // ignored it would advertise a "from" higher than the cheapest thing on
        // the page.
        $package = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'retail_price' => 399.00,
            'sale_price' => 79.00,
        ]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'sale_price' => 99.00]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'sale_price' => 249.00]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.price_range.from', 79)
            ->assertJsonPath('data.price_range.to', 249)
            ->assertJsonPath('data.is_on_sale', true);
    }

    public function test_a_package_with_no_plans_still_reports_its_own_price(): void
    {
        // A package can be a single product. Its price is then the whole range,
        // and the detail page has something to show — where before both the
        // price block and the range were absent.
        $package = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'retail_price' => 399.00,
            'sale_price' => null,
        ]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.price.effective', 399)
            ->assertJsonPath('data.price_range.from', 399)
            ->assertJsonPath('data.price_range.to', 399);
    }

    public function test_the_package_price_block_is_served_at_all(): void
    {
        // The regression that started this: an operator set the package to $399,
        // the save worked, and the storefront kept showing the default plan's
        // price, because nothing ever emitted the package's own.
        $package = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'retail_price' => 399.00,
            'sale_price' => 349.00,
            'price_suffix' => '/mo',
        ]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'retail_price' => 279.99]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonPath('data.price.retail', 399)
            ->assertJsonPath('data.price.sale', 349)
            ->assertJsonPath('data.price.effective', 349)
            ->assertJsonPath('data.price.suffix', '/mo');
    }

    public function test_package_show_returns_404_for_draft(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Draft]);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")->assertNotFound();
    }

    // ── Gap 8: is_on_sale ────────────────────────────────────────────────

    public function test_package_is_on_sale_true_when_any_plan_has_sale_price(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'retail_price' => 199, 'sale_price' => 149]);

        $this->getJson('/api/v1/catalog/packages')
            ->assertOk()
            ->assertJsonPath('data.0.is_on_sale', true);
    }

    public function test_package_is_on_sale_false_when_no_plan_has_sale_price(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'retail_price' => 199, 'sale_price' => null]);

        $this->getJson('/api/v1/catalog/packages')
            ->assertOk()
            ->assertJsonPath('data.0.is_on_sale', false);
    }

    // ── Gap 9: is_in_stock ───────────────────────────────────────────────

    public function test_package_response_includes_is_in_stock(): void
    {
        Package::factory()->create(['status' => CatalogStatus::Published, 'is_in_stock' => true]);

        $this->getJson('/api/v1/catalog/packages')
            ->assertOk()
            ->assertJsonPath('data.0.is_in_stock', true);
    }

    public function test_packages_filter_in_stock_excludes_out_of_stock(): void
    {
        Package::factory()->create(['status' => CatalogStatus::Published, 'is_in_stock' => true, 'name' => 'Available']);
        Package::factory()->create(['status' => CatalogStatus::Published, 'is_in_stock' => false, 'name' => 'Unavailable']);

        $this->getJson('/api/v1/catalog/packages?in_stock=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Available');
    }

    // ── Gap 7: price_min / price_max ─────────────────────────────────────

    public function test_packages_filter_price_min_uses_plan_effective_price(): void
    {
        $cheap = Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Cheap']);
        Plan::factory()->create(['package_id' => $cheap->id, 'status' => CatalogStatus::Published, 'retail_price' => 50, 'sale_price' => null]);

        $expensive = Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Expensive']);
        Plan::factory()->create(['package_id' => $expensive->id, 'status' => CatalogStatus::Published, 'retail_price' => 200, 'sale_price' => null]);

        $this->getJson('/api/v1/catalog/packages?price_min=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Expensive');
    }

    public function test_packages_filter_price_max_uses_plan_effective_price(): void
    {
        $cheap = Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Cheap']);
        Plan::factory()->create(['package_id' => $cheap->id, 'status' => CatalogStatus::Published, 'retail_price' => 50, 'sale_price' => null]);

        $expensive = Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Expensive']);
        Plan::factory()->create(['package_id' => $expensive->id, 'status' => CatalogStatus::Published, 'retail_price' => 200, 'sale_price' => null]);

        $this->getJson('/api/v1/catalog/packages?price_max=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cheap');
    }

    public function test_packages_price_filter_uses_sale_price_when_set(): void
    {
        // retail=$200 but on sale for $80 — should appear in price_max=100 results
        $package = Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'On Sale']);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'retail_price' => 200, 'sale_price' => 80]);

        $this->getJson('/api/v1/catalog/packages?price_max=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'On Sale');
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
