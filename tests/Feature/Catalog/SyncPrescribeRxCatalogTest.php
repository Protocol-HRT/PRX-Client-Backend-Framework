<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SyncPrescribeRxCatalogAction;
use App\Enums\BillingMode;
use App\Enums\CatalogStatus;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductClass;
use App\Models\Catalog\ProductType;
use App\Services\PrescribeRx\Client;
use Database\Seeders\CatalogVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncPrescribeRxCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const CLASS_ID = '11111111-1111-1111-1111-111111111111';

    private const TYPE_ID = '22222222-2222-2222-2222-222222222222';

    private const PRODUCT_ID = '33333333-3333-3333-3333-333333333333';

    private const PACKAGE_ID = '44444444-4444-4444-4444-444444444444';

    private const PLAN_ID = '55555555-5555-5555-5555-555555555555';

    private const INGREDIENT_A = '66666666-6666-6666-6666-666666666666';

    private const INGREDIENT_B = '77777777-7777-7777-7777-777777777777';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogVocabularySeeder::class);
    }

    private function mockClient(array $overrides = []): void
    {
        $this->mock(Client::class, function (MockInterface $mock) use ($overrides): void {
            $mock->shouldReceive('listPrxProductClasses')->andReturn(
                $overrides['classes'] ?? [['id' => self::CLASS_ID, 'name' => 'Peptides', 'description' => null, 'is_active' => true]]
            );
            $mock->shouldReceive('listPrxProductTypes')->andReturn(
                $overrides['types'] ?? [['id' => self::TYPE_ID, 'product_class_id' => self::CLASS_ID, 'name' => 'Blends', 'rx_required' => true, 'is_active' => true]]
            );
            $mock->shouldReceive('listAllPrxProducts')->andReturn(
                $overrides['products'] ?? [$this->prxProduct()]
            );
            $mock->shouldReceive('getPrxProduct')->andReturn(
                $overrides['productDetail'] ?? []
            );
            $mock->shouldReceive('listAllPrxPackages')->andReturn(
                $overrides['packages'] ?? [$this->prxPackage()]
            );
        });
    }

    private function prxProduct(array $overrides = []): array
    {
        return array_merge([
            'id' => self::PRODUCT_ID,
            'sku' => 'KLOW-10MG',
            'name' => 'Klow Blend',
            'short_description' => 'Peptide blend',
            'description' => 'Long description',
            'rx_required' => true,
            'is_active' => true,
            'image_url' => null,
            'product_class_id' => self::CLASS_ID,
            'product_class' => ['id' => self::CLASS_ID, 'name' => 'Peptides'],
            'product_type_id' => self::TYPE_ID,
            'product_type' => ['id' => self::TYPE_ID, 'name' => 'Blends'],
            'pricing' => ['retail_price' => 199.99, 'consumer_price' => null, 'cost' => 55.25, 'price' => 199.99, 'price_type' => 'retail'],
            'ingredients' => [
                ['id' => self::INGREDIENT_A, 'name' => 'GHK-Cu', 'quantity' => '50mg'],
                ['id' => self::INGREDIENT_B, 'name' => 'BPC-157', 'quantity' => '10 mg / 3 ml'],
            ],
        ], $overrides);
    }

    private function prxPackage(): array
    {
        return [
            'id' => self::PACKAGE_ID,
            'package_number' => 'PKG-001',
            'name' => 'Klow Stack',
            'description' => 'Bundle',
            'pricing' => ['retail_price' => 499.0, 'consumer_price' => null, 'cost' => 150.0, 'price' => 499.0, 'price_type' => 'retail'],
            'items' => [
                ['id' => 'item-1', 'product_id' => self::PRODUCT_ID, 'quantity' => 1],
            ],
            'plans' => [
                [
                    'id' => self::PLAN_ID,
                    'name' => 'Monthly',
                    'price' => 179.99,
                    'term_months' => 1,
                    'subscription_interval_days' => 30,
                    'is_default' => true,
                ],
            ],
        ];
    }

    public function test_sync_creates_products_with_classification_ingredients_and_cost(): void
    {
        $this->mockClient();

        $stats = app(SyncPrescribeRxCatalogAction::class)->execute();

        $this->assertSame(1, $stats['products']['new']);
        $this->assertSame(2, $stats['ingredients']['synced']);

        $product = Product::where('provider_product_id', self::PRODUCT_ID)->firstOrFail();

        $this->assertSame(CatalogStatus::Pending, $product->status);
        $this->assertTrue($product->rx_required);
        $this->assertSame('55.25', $product->cost);

        $this->assertSame('Peptides', $product->productClass->name);
        $this->assertSame(self::CLASS_ID, $product->productClass->provider_product_class_id);
        $this->assertSame('Blends', $product->productType->name);
        $this->assertSame($product->productClass->id, $product->productType->product_class_id);

        $ingredients = $product->ingredients;
        $this->assertCount(2, $ingredients);

        $ghk = $ingredients->firstWhere('name', 'GHK-Cu');
        $this->assertSame(self::INGREDIENT_A, $ghk->provider_ingredient_id);
        $this->assertSame('50 mg', $ghk->pivot->potencyLabel());

        $bpc = $ingredients->firstWhere('name', 'BPC-157');
        $this->assertSame('10 mg / 3 ml', $bpc->pivot->potencyLabel());
        $this->assertSame('10 mg / 3 ml', $bpc->pivot->provider_quantity_label);
    }

    public function test_sync_creates_package_with_cost_and_plan_billing_mode(): void
    {
        $this->mockClient();

        app(SyncPrescribeRxCatalogAction::class)->execute();

        $package = Package::where('provider_package_id', self::PACKAGE_ID)->firstOrFail();
        $this->assertSame('150.00', $package->cost);
        $this->assertTrue($package->products()->where('provider_product_id', self::PRODUCT_ID)->exists());

        $plan = Plan::where('provider_plan_id', self::PLAN_ID)->firstOrFail();
        $this->assertSame(BillingMode::Recurring, $plan->billing_mode);
        $this->assertTrue($plan->is_recurring);
    }

    public function test_resync_updates_provider_truth_but_preserves_curated_content(): void
    {
        $this->mockClient();
        app(SyncPrescribeRxCatalogAction::class)->execute();

        $product = Product::where('provider_product_id', self::PRODUCT_ID)->firstOrFail();
        $product->update([
            'status' => CatalogStatus::Published,
            'name' => 'Curated Marketing Name',
            'description' => 'Curated description',
            'cost' => 1.00,
        ]);

        app(SyncPrescribeRxCatalogAction::class)->execute();
        $product->refresh();

        $this->assertSame('Curated Marketing Name', $product->name);
        $this->assertSame('Curated description', $product->description);
        $this->assertSame('55.25', $product->cost);
        $this->assertNotNull($product->product_class_id);
    }

    public function test_sync_reuses_existing_lookup_rows_and_maps_ingredients_by_name(): void
    {
        $class = ProductClass::factory()->create(['name' => 'Admin Renamed Class', 'provider_product_class_id' => self::CLASS_ID]);
        $type = ProductType::factory()->create(['name' => 'Admin Renamed Type', 'provider_product_type_id' => self::TYPE_ID]);
        $ingredient = Ingredient::factory()->create(['name' => 'ghk-cu', 'provider_ingredient_id' => null]);

        $this->mockClient();
        app(SyncPrescribeRxCatalogAction::class)->execute();

        $product = Product::where('provider_product_id', self::PRODUCT_ID)->firstOrFail();

        $this->assertSame($class->id, $product->product_class_id);
        $this->assertSame('Admin Renamed Class', $product->productClass->name);
        $this->assertSame($type->id, $product->product_type_id);

        $this->assertSame(self::INGREDIENT_A, $ingredient->fresh()->provider_ingredient_id);
        $this->assertSame(2, Ingredient::count());
    }

    public function test_sync_falls_back_to_product_detail_for_ingredients(): void
    {
        $listItem = $this->prxProduct();
        unset($listItem['ingredients']);

        $this->mockClient([
            'products' => [$listItem],
            'productDetail' => $this->prxProduct(),
        ]);

        $stats = app(SyncPrescribeRxCatalogAction::class)->execute();

        $this->assertSame(2, $stats['ingredients']['synced']);
        $product = Product::where('provider_product_id', self::PRODUCT_ID)->firstOrFail();
        $this->assertCount(2, $product->ingredients);
    }

    public function test_sync_tolerates_missing_classification_endpoints(): void
    {
        $this->mock(Client::class, function (MockInterface $mock): void {
            $mock->shouldReceive('listPrxProductClasses')->andThrow(new \RuntimeException('404'));
            $mock->shouldReceive('listPrxProductTypes')->andThrow(new \RuntimeException('404'));
            $mock->shouldReceive('listAllPrxProducts')->andReturn([$this->prxProduct()]);
            $mock->shouldReceive('listAllPrxPackages')->andReturn([]);
        });

        app(SyncPrescribeRxCatalogAction::class)->execute();

        $product = Product::where('provider_product_id', self::PRODUCT_ID)->firstOrFail();
        $this->assertSame('Peptides', $product->productClass->name);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
