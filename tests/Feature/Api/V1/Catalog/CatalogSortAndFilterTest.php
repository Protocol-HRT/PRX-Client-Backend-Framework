<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductClass;
use App\Models\Catalog\ProductForm;
use App\Models\Catalog\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSortAndFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_sort_by_price_uses_effective_price(): void
    {
        Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Mid', 'retail_price' => 100, 'sale_price' => null]);
        Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Cheap', 'retail_price' => 300, 'sale_price' => 50]);
        Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Expensive', 'retail_price' => 200, 'sale_price' => null]);

        $names = $this->getJson('/api/v1/catalog/products?sort=price')
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Cheap', 'Mid', 'Expensive'], $names);

        $names = $this->getJson('/api/v1/catalog/products?sort=-price')
            ->assertOk()
            ->json('data.*.name');

        $this->assertSame(['Expensive', 'Mid', 'Cheap'], $names);
    }

    public function test_products_sort_by_name_and_newest(): void
    {
        $older = Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Zeta', 'created_at' => now()->subDay()]);
        $newer = Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Alpha', 'created_at' => now()]);

        $this->assertSame(
            ['Alpha', 'Zeta'],
            $this->getJson('/api/v1/catalog/products?sort=name')->json('data.*.name')
        );

        $this->assertSame(
            ['Zeta', 'Alpha'],
            $this->getJson('/api/v1/catalog/products?sort=-name')->json('data.*.name')
        );

        $this->assertSame(
            ['Alpha', 'Zeta'],
            $this->getJson('/api/v1/catalog/products?sort=newest')->json('data.*.name')
        );
    }

    public function test_products_invalid_sort_falls_back_to_position(): void
    {
        // Sortable's sort_when_creating ignores explicit positions on create;
        // set them via update afterwards.
        $b = Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'B']);
        $a = Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'A']);
        $b->update(['position' => 2]);
        $a->update(['position' => 1]);

        $this->assertSame(
            ['A', 'B'],
            $this->getJson('/api/v1/catalog/products?sort=drop-table')->json('data.*.name')
        );
    }

    public function test_products_filter_by_class_type_and_form_slugs(): void
    {
        $peptides = ProductClass::factory()->create(['name' => 'Peptides']);
        $blends = ProductType::factory()->create(['name' => 'Blends']);
        $vial = ProductForm::factory()->create(['name' => 'Vial']);

        Product::factory()->create([
            'status' => CatalogStatus::Published,
            'name' => 'Match',
            'product_class_id' => $peptides->id,
            'product_type_id' => $blends->id,
            'product_form_id' => $vial->id,
        ]);
        Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Other']);

        foreach ([
            "class={$peptides->slug}",
            "type={$blends->slug}",
            "form={$vial->slug}",
            "class={$peptides->slug}&type={$blends->slug}&form={$vial->slug}",
        ] as $query) {
            $response = $this->getJson("/api/v1/catalog/products?{$query}")->assertOk();
            $this->assertSame(['Match'], $response->json('data.*.name'), "query: {$query}");
        }
    }

    public function test_packages_support_sort_param(): void
    {
        Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Pricey', 'retail_price' => 900]);
        Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Budget', 'retail_price' => 90]);

        $this->assertSame(
            ['Budget', 'Pricey'],
            $this->getJson('/api/v1/catalog/packages?sort=price')->json('data.*.name')
        );

        $this->assertSame(
            ['Budget', 'Pricey'],
            $this->getJson('/api/v1/catalog/packages?sort=name')->json('data.*.name')
        );
    }
}
