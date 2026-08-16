<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogRelationType;
use App\Enums\CatalogStatus;
use App\Enums\InventoryStatus;
use App\Models\Catalog\AdministrationMethod;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\MeasurementUnit;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductClass;
use App\Models\Catalog\ProductCoa;
use App\Models\Catalog\ProductForm;
use App\Models\Catalog\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_includes_classification(): void
    {
        $class = ProductClass::factory()->create(['name' => 'Peptides']);
        $type = ProductType::factory()->create(['name' => 'Blend', 'product_class_id' => $class->id]);
        $form = ProductForm::factory()->volumetric()->create(['name' => 'Vial (Lyophilized)']);
        $method = AdministrationMethod::factory()->create(['name' => 'Subcutaneous Injection']);
        $unit = MeasurementUnit::factory()->create(['abbreviation' => 'mg']);

        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'product_class_id' => $class->id,
            'product_type_id' => $type->id,
            'product_form_id' => $form->id,
            'administration_method_id' => $method->id,
            'volume' => 10,
            'volume_unit_id' => $unit->id,
            'rx_required' => true,
            'is_controlled_substance' => false,
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.classification.class.name', 'Peptides')
            ->assertJsonPath('data.classification.type.name', 'Blend')
            ->assertJsonPath('data.classification.form.name', 'Vial (Lyophilized)')
            ->assertJsonPath('data.classification.administration_method.name', 'Subcutaneous Injection')
            ->assertJsonPath('data.volume.value', 10)
            ->assertJsonPath('data.volume.unit', 'mg')
            ->assertJsonPath('data.rx_required', true)
            ->assertJsonPath('data.is_controlled_substance', false);
    }

    public function test_product_show_includes_ingredients_with_potency_labels(): void
    {
        $mg = MeasurementUnit::factory()->create(['abbreviation' => 'mg']);
        $ml = MeasurementUnit::factory()->create(['abbreviation' => 'ml']);

        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        $dry = Ingredient::factory()->create(['name' => 'GHK-Cu']);
        $liquid = Ingredient::factory()->create(['name' => 'BPC-157']);

        $product->ingredients()->attach($dry->id, [
            'concentration' => 50,
            'concentration_unit_id' => $mg->id,
            'position' => 0,
        ]);
        $product->ingredients()->attach($liquid->id, [
            'concentration' => 10,
            'concentration_unit_id' => $mg->id,
            'per_volume' => 3,
            'per_volume_unit_id' => $ml->id,
            'position' => 1,
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'data.ingredients')
            ->assertJsonPath('data.ingredients.0.name', 'GHK-Cu')
            ->assertJsonPath('data.ingredients.0.label', '50 mg')
            ->assertJsonPath('data.ingredients.1.name', 'BPC-157')
            ->assertJsonPath('data.ingredients.1.label', '10 mg / 3 ml');
    }

    public function test_product_show_includes_visible_coas_only(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        ProductCoa::factory()->create(['product_id' => $product->id, 'batch_number' => 'BATCH-VISIBLE']);
        ProductCoa::factory()->hidden()->create(['product_id' => $product->id, 'batch_number' => 'BATCH-HIDDEN']);

        $response = $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.coas')
            ->assertJsonPath('data.coas.0.batch_number', 'BATCH-VISIBLE');

        $this->assertStringContainsString('.pdf', $response->json('data.coas.0.file_url'));
    }

    public function test_cost_is_never_exposed_on_public_api(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'cost' => 42.50,
        ]);

        $index = $this->getJson('/api/v1/catalog/products')->assertOk();
        $show = $this->getJson("/api/v1/catalog/products/{$product->slug}")->assertOk();

        $this->assertStringNotContainsString('"cost"', $index->getContent());
        $this->assertStringNotContainsString('"cost"', $show->getContent());
    }

    public function test_related_and_pairs_with_expose_published_items_only(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        $relatedProduct = Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Related Product']);
        $draftProduct = Product::factory()->create(['status' => CatalogStatus::Draft]);
        $pairedPackage = Package::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Paired Stack']);

        $product->catalogRelations()->createMany([
            [
                'related_type' => Product::class,
                'related_id' => $relatedProduct->id,
                'relation_type' => CatalogRelationType::Related,
            ],
            [
                'related_type' => Product::class,
                'related_id' => $draftProduct->id,
                'relation_type' => CatalogRelationType::Related,
            ],
            [
                'related_type' => Package::class,
                'related_id' => $pairedPackage->id,
                'relation_type' => CatalogRelationType::PairsWith,
            ],
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.related')
            ->assertJsonPath('data.related.0.name', 'Related Product')
            ->assertJsonPath('data.related.0.type', 'product')
            ->assertJsonCount(1, 'data.pairs_with')
            ->assertJsonPath('data.pairs_with.0.name', 'Paired Stack')
            ->assertJsonPath('data.pairs_with.0.type', 'package');
    }

    public function test_inventory_status_drives_is_in_stock(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'is_in_stock' => true,
        ]);

        $product->update(['inventory_status' => InventoryStatus::OutOfStock]);
        $this->assertFalse($product->fresh()->is_in_stock);

        $product->update(['inventory_status' => InventoryStatus::BackOrdered]);
        $this->assertTrue($product->fresh()->is_in_stock);

        $product->update(['inventory_status' => InventoryStatus::Discontinued]);
        $this->assertFalse($product->fresh()->is_in_stock);

        $product->update(['inventory_status' => InventoryStatus::InStock]);
        $this->assertTrue($product->fresh()->is_in_stock);
    }
}
