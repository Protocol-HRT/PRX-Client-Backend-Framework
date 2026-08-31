<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogRelationType;
use App\Enums\CatalogStatus;
use App\Enums\InventoryStatus;
use App\Models\Catalog\AdministrationMethod;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\MeasurementUnit;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
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

    /**
     * REWRITTEN. This used to assert that a rail card priced a package from
     * its default plan, and it passed for as long as packages had no price
     * columns of their own. Once they did, the assertion pinned a real defect
     * in place: a $399 buy-once stack advertising its subscription's $279.99
     * on every upsell card. A test that encodes yesterday's workaround stops
     * being a safety net and becomes the thing holding the bug up.
     *
     * The rule now: a package prices ITSELF, and carries a range spanning its
     * plans and its own price so a stack sold only through plans can still
     * say "From $X" rather than showing nothing.
     */
    public function test_relation_light_cards_price_a_package_by_itself_with_a_range(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        $pricedPackage = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'name' => 'Priced Stack',
            'retail_price' => 399.00,
            'sale_price' => null,
            'price_suffix' => null,
        ]);
        $unpricedPackage = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'name' => 'Unpriced Stack',
            'retail_price' => null,
            'sale_price' => null,
            'price_suffix' => null,
        ]);

        Plan::factory()->for($pricedPackage)->create([
            'retail_price' => 249.00,
            'price_suffix' => '/mo',
            'is_default' => false,
            'position' => 0,
        ]);
        Plan::factory()->for($pricedPackage)->default()->create([
            'retail_price' => 199.00,
            'sale_price' => 149.00,
            'price_suffix' => '/mo',
            'position' => 1,
        ]);

        $product->catalogRelations()->createMany([
            [
                'related_type' => Package::class,
                'related_id' => $pricedPackage->id,
                'relation_type' => CatalogRelationType::PairsWith,
            ],
            [
                'related_type' => Package::class,
                'related_id' => $unpricedPackage->id,
                'relation_type' => CatalogRelationType::PairsWith,
            ],
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.pairs_with.0.name', 'Priced Stack')
            // Its own price, NOT the default plan's 149.
            ->assertJsonPath('data.pairs_with.0.price.retail', 399)
            ->assertJsonPath('data.pairs_with.0.price.effective', 399)
            // The package sets no suffix; the plans say "/mo". Pins that the
            // substituted plan's suffix no longer leaks onto the card either.
            ->assertJsonPath('data.pairs_with.0.price.suffix', null)
            // The cheapest plan's sale price through to the buy-once price.
            ->assertJsonPath('data.pairs_with.0.price_range.from', 149)
            ->assertJsonPath('data.pairs_with.0.price_range.to', 399)
            ->assertJsonPath('data.pairs_with.1.name', 'Unpriced Stack')
            ->assertJsonPath('data.pairs_with.1.price.retail', null)
            ->assertJsonPath('data.pairs_with.1.price.sale', null)
            ->assertJsonPath('data.pairs_with.1.price.effective', null)
            // No plans and no price of its own: an empty range, not a zero.
            ->assertJsonPath('data.pairs_with.1.price_range.from', null);
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

    public function test_inventory_status_ships_a_display_label_beside_the_raw_value(): void
    {
        // The storefront printed "in_stock" as the stock badge on the two
        // products where an operator had set the field; the five with it null
        // fell through to a hardcoded frontend string and looked right, which
        // is why the two detail pages disagreed. Every case is asserted, not
        // just the happy one — a label map is exactly the kind of thing that
        // covers one case and drops three.
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'inventory_status' => InventoryStatus::BackOrdered,
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.inventory_status', 'back_ordered')
            ->assertJsonPath('data.inventory_status_label', 'Back Ordered');

        foreach ([
            [InventoryStatus::InStock, 'in_stock', 'In Stock'],
            [InventoryStatus::OutOfStock, 'out_of_stock', 'Out of Stock'],
            [InventoryStatus::Discontinued, 'discontinued', 'Discontinued'],
        ] as [$case, $value, $label]) {
            $product->update(['inventory_status' => $case]);

            $this->getJson("/api/v1/catalog/products/{$product->slug}")
                ->assertOk()
                ->assertJsonPath('data.inventory_status', $value)
                ->assertJsonPath('data.inventory_status_label', $label);
        }
    }

    public function test_a_product_with_no_inventory_status_ships_a_null_label(): void
    {
        // Most products leave it unset. The label must be null rather than an
        // empty string, so the frontend's own fallback is reached rather than
        // a blank badge being rendered as though it were a status.
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'inventory_status' => null,
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.inventory_status', null)
            ->assertJsonPath('data.inventory_status_label', null);
    }
}
