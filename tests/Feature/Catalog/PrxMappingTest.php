<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\ImportPrxCatalogItemAction;
use App\Actions\Catalog\MapProviderCatalogItemAction;
use App\Enums\CatalogStatus;
use App\Filament\Pages\PrxCatalog;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\User;
use App\Services\PrescribeRx\Client;
use App\Services\PrescribeRx\RemoteCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrxMappingTest extends TestCase
{
    use RefreshDatabase;

    private function mockRemote(array $products = [], array $packages = []): void
    {
        $this->mock(Client::class, function (MockInterface $mock) use ($products, $packages): void {
            $mock->shouldReceive('listAllPrxProducts')->andReturn($products);
            $mock->shouldReceive('listAllPrxPackages')->andReturn($packages);
        });
    }

    public function test_suggestions_rank_exact_sku_match_first(): void
    {
        $this->mockRemote(products: [
            ['id' => 'uuid-a', 'sku' => 'OTHER-SKU', 'name' => 'Completely Different'],
            ['id' => 'uuid-b', 'sku' => 'KLOW-10MG', 'name' => 'Unrelated Name'],
            ['id' => 'uuid-c', 'sku' => 'XX', 'name' => 'Klow Blend Similar'],
        ]);

        $product = Product::factory()->create([
            'name' => 'Klow Blend',
            'provider_product_id' => null,
            'provider_product_sku' => 'KLOW-10MG',
        ]);

        $suggestions = app(RemoteCatalog::class)->suggestionsForProduct($product);

        $this->assertSame('uuid-b', $suggestions->first()['id']);
        $this->assertSame(100, $suggestions->first()['match_score']);
    }

    public function test_suggestions_rank_by_name_similarity_without_sku(): void
    {
        $this->mockRemote(products: [
            ['id' => 'uuid-a', 'sku' => 'A', 'name' => 'Zinc Tablets'],
            ['id' => 'uuid-b', 'sku' => 'B', 'name' => 'Klow Blend'],
        ]);

        $product = Product::factory()->create(['name' => 'Klow Blend', 'provider_product_sku' => null]);

        $suggestions = app(RemoteCatalog::class)->suggestionsForProduct($product);

        $this->assertSame('uuid-b', $suggestions->first()['id']);
        $this->assertSame(100, $suggestions->first()['match_score']);
    }

    public function test_map_action_sets_and_clears_provider_ids(): void
    {
        $product = Product::factory()->create(['provider_product_id' => null]);
        $package = Package::factory()->create(['provider_package_id' => null]);

        $action = app(MapProviderCatalogItemAction::class);

        $action->execute($product, 'prod-uuid', 'PROD-SKU');
        $this->assertSame('prod-uuid', $product->fresh()->provider_product_id);
        $this->assertSame('PROD-SKU', $product->fresh()->provider_product_sku);

        $action->execute($package, 'pkg-uuid', 'PKG-001');
        $this->assertSame('pkg-uuid', $package->fresh()->provider_package_id);

        $action->execute($product->fresh(), null, null);
        $this->assertNull($product->fresh()->provider_product_id);
        $this->assertNull($product->fresh()->provider_product_sku);
    }

    public function test_import_action_creates_pending_mapped_shells(): void
    {
        $action = app(ImportPrxCatalogItemAction::class);

        $product = $action->executeProduct([
            'id' => 'prod-uuid',
            'sku' => 'SKU-1',
            'name' => 'Imported Product',
            'short_description' => 'Short',
            'rx_required' => true,
            'pricing' => ['retail_price' => 120, 'cost' => 40],
        ]);

        $this->assertSame(CatalogStatus::Pending, $product->status);
        $this->assertSame('prod-uuid', $product->provider_product_id);
        $this->assertTrue($product->rx_required);
        $this->assertSame('40.00', $product->cost);

        $package = $action->executePackage([
            'id' => 'pkg-uuid',
            'package_number' => 'PKG-9',
            'name' => 'Imported Package',
            'pricing' => ['retail_price' => 300],
        ]);

        $this->assertSame(CatalogStatus::Pending, $package->status);
        $this->assertSame('pkg-uuid', $package->provider_package_id);
        $this->assertSame('PKG-9', $package->provider_package_sku);
    }

    public function test_prx_catalog_page_renders_for_super_admin(): void
    {
        config(['prescribe-rx.stub' => true]);

        Role::findOrCreate('super_admin', 'web');

        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get(PrxCatalog::getUrl())
            ->assertOk();
    }
}
