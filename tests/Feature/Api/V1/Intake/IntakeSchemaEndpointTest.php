<?php

namespace Tests\Feature\Api\V1\Intake;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for GET /api/v1/intake/schema
 *
 * Resolution order:
 *   1. product.provider_encounter_type_id  (direct product override)
 *   2. category.provider_encounter_type_id (first mapped category)
 *   3. package.provider_encounter_type_id  (package-level fallback)
 *   4. null → 422
 */
class IntakeSchemaEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_schema_from_product_level_encounter_type(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => 'enc-testosterone',
        ]);

        $this->getJson("/api/v1/intake/schema?products[]={$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.encounter_type_id', 'enc-testosterone')
            ->assertJsonStructure(['data' => ['encounter_type_id', 'schema']]);
    }

    public function test_resolves_encounter_type_from_category_when_product_has_none(): void
    {
        $category = Category::factory()->create([
            'provider_encounter_type_id' => 'enc-weight-loss',
        ]);

        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => null,
        ]);

        $product->categories()->attach($category);

        $this->getJson("/api/v1/intake/schema?products[]={$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.encounter_type_id', 'enc-weight-loss');
    }

    public function test_product_level_takes_priority_over_category(): void
    {
        $category = Category::factory()->create([
            'provider_encounter_type_id' => 'enc-category',
        ]);

        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => 'enc-product-override',
        ]);

        $product->categories()->attach($category);

        $this->getJson("/api/v1/intake/schema?products[]={$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.encounter_type_id', 'enc-product-override');
    }

    public function test_resolves_from_package_level_when_no_product_mapping(): void
    {
        $package = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => 'enc-package-level',
        ]);

        $this->getJson("/api/v1/intake/schema?packages[]={$package->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.encounter_type_id', 'enc-package-level');
    }

    public function test_returns_422_when_no_encounter_type_mapped(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => null,
        ]);

        $this->getJson("/api/v1/intake/schema?products[]={$product->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'encounter type'));
    }

    public function test_returns_422_with_no_params(): void
    {
        $this->getJson('/api/v1/intake/schema')
            ->assertStatus(422);
    }

    public function test_schema_key_is_present_in_response(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => 'enc-abc',
        ]);

        $response = $this->getJson("/api/v1/intake/schema?products[]={$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['encounter_type_id', 'schema']]);

        // NullTelehealthProvider returns [] — confirm the key exists and is an array
        $this->assertIsArray($response->json('data.schema'));
    }

    public function test_accepts_both_products_and_packages_params(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => 'enc-combo',
        ]);

        $package = Package::factory()->create([
            'status' => CatalogStatus::Published,
            'provider_encounter_type_id' => 'enc-package',
        ]);

        $this->getJson("/api/v1/intake/schema?products[]={$product->id}&packages[]={$package->id}")
            ->assertStatus(200)
            // Product-level wins over package
            ->assertJsonPath('data.encounter_type_id', 'enc-combo');
    }
}
