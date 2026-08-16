<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\CreateProductAction;
use App\Actions\Catalog\UpdateProductAction;
use App\Data\Catalog\ProductData;
use App\Models\Catalog\AdministrationMethod;
use App\Models\Catalog\MeasurementUnit;
use App\Models\Catalog\ProductClass;
use App\Models\Catalog\ProductForm;
use App\Models\Catalog\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the admin create/update path: every field the
 * Filament form submits must survive the DTO → Action round-trip. (The
 * provider_* renames previously left the DTO writing to dropped columns,
 * silently losing provider mapping, badge, and highlights edits.)
 */
class ProductActionFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_action_persists_provider_mapping_and_new_catalog_fields(): void
    {
        $class = ProductClass::factory()->create();
        $type = ProductType::factory()->create();
        $form = ProductForm::factory()->create();
        $method = AdministrationMethod::factory()->create();
        $unit = MeasurementUnit::factory()->create();

        $product = app(CreateProductAction::class)->execute(ProductData::validateAndCreate([
            'name' => 'Klow Blend',
            'provider_product_id' => '019bde3e-e71a-7225-98fe-8f28b4cbcd77',
            'provider_product_sku' => 'KLOW-10MG',
            'badge_text' => 'New',
            'highlights' => [['item' => 'GHK-Cu 50mg']],
            'product_class_id' => $class->id,
            'product_type_id' => $type->id,
            'product_form_id' => $form->id,
            'administration_method_id' => $method->id,
            'volume' => 10,
            'volume_unit_id' => $unit->id,
            'inventory_status' => 'in_stock',
            'is_controlled_substance' => true,
            'rx_required' => true,
            'cost' => 55.25,
        ]));

        $this->assertSame('019bde3e-e71a-7225-98fe-8f28b4cbcd77', $product->provider_product_id);
        $this->assertSame('KLOW-10MG', $product->provider_product_sku);
        $this->assertSame('New', $product->badge_text);
        $this->assertSame([['item' => 'GHK-Cu 50mg']], $product->highlights);
        $this->assertSame($class->id, $product->product_class_id);
        $this->assertSame($type->id, $product->product_type_id);
        $this->assertSame($form->id, $product->product_form_id);
        $this->assertSame($method->id, $product->administration_method_id);
        $this->assertSame('10.0000', $product->volume);
        $this->assertSame($unit->id, $product->volume_unit_id);
        $this->assertTrue($product->is_controlled_substance);
        $this->assertTrue($product->rx_required);
        $this->assertSame('55.25', $product->cost);
    }

    public function test_update_action_persists_provider_mapping(): void
    {
        $product = app(CreateProductAction::class)->execute(ProductData::validateAndCreate([
            'name' => 'Plain Product',
        ]));

        $updated = app(UpdateProductAction::class)->execute($product, ProductData::validateAndCreate([
            'name' => 'Plain Product',
            'slug' => $product->slug,
            'provider_product_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'badge_text' => 'Best Seller',
        ]));

        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $updated->provider_product_id);
        $this->assertSame('Best Seller', $updated->badge_text);
    }
}
