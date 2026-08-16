<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_exposes_published_term_plans_in_position_order(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        // Sortable's sort_when_creating ignores explicit position values —
        // create in display order (see feedback-sortable-trait-position).
        Plan::factory()->for($product)->default()->create([
            'name' => 'Monthly Plan',
            'retail_price' => 99.00,
        ]);
        Plan::factory()->for($product)->quarterly()->create([
            'name' => '3-Month Plan',
            'retail_price' => 279.00,
        ]);
        Plan::factory()->for($product)->draft()->create([
            'name' => 'Hidden Plan',
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'data.plans')
            ->assertJsonPath('data.plans.0.name', 'Monthly Plan')
            ->assertJsonPath('data.plans.0.is_default', true)
            ->assertJsonPath('data.plans.0.price.effective', 99)
            ->assertJsonPath('data.plans.1.name', '3-Month Plan')
            ->assertJsonPath('data.plans.1.billing.term_months', 3);
    }

    public function test_product_index_does_not_expose_plans(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        Plan::factory()->for($product)->create();

        $response = $this->getJson('/api/v1/catalog/products')->assertOk();

        $this->assertArrayNotHasKey('plans', $response->json('data.0'));
    }

    public function test_plan_cannot_belong_to_both_package_and_product(): void
    {
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        Plan::factory()->for($package)->for($product)->create();
    }

    public function test_product_plans_do_not_leak_into_package_plan_lists(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        Plan::factory()->for($package)->create(['name' => 'Package Plan']);
        Plan::factory()->for($product)->create(['name' => 'Product Plan']);

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.plans')
            ->assertJsonPath('data.plans.0.name', 'Package Plan');
    }
}
