<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_returns_published_products_only(): void
    {
        Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Visible']);
        Product::factory()->create(['status' => CatalogStatus::Draft, 'name' => 'Hidden']);

        $this->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible');
    }

    public function test_products_index_response_shape(): void
    {
        Product::factory()->create(['status' => CatalogStatus::Published]);

        $this->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'price', 'badge_text', 'highlights', 'is_featured', 'requires_lab', 'provider']],
                'links',
                'meta',
            ]);
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $cat = Category::factory()->create(['is_visible' => true]);
        $inCategory = Product::factory()->create(['status' => CatalogStatus::Published]);
        $notInCategory = Product::factory()->create(['status' => CatalogStatus::Published]);
        $inCategory->categories()->attach($cat->id);

        $this->getJson("/api/v1/catalog/products?category={$cat->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inCategory->id);
    }

    public function test_products_can_be_filtered_by_tag(): void
    {
        $tag = Tag::factory()->create(['is_visible' => true]);
        $tagged = Product::factory()->create(['status' => CatalogStatus::Published]);
        Product::factory()->create(['status' => CatalogStatus::Published]);
        $tagged->tags()->attach($tag->id);

        $this->getJson("/api/v1/catalog/products?tag={$tag->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_products_can_be_filtered_by_featured(): void
    {
        Product::factory()->create(['status' => CatalogStatus::Published, 'is_featured' => true]);
        Product::factory()->create(['status' => CatalogStatus::Published, 'is_featured' => false]);

        $this->getJson('/api/v1/catalog/products?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_products_can_be_searched_by_name(): void
    {
        Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Testosterone Cream']);
        Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'Progesterone Pill']);

        $this->getJson('/api/v1/catalog/products?search=Testosterone')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Testosterone Cream');
    }

    public function test_product_show_returns_single_published_product(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'highlights' => [['item' => 'Fast shipping'], ['item' => 'Physician reviewed']],
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonPath('data.highlights', ['Fast shipping', 'Physician reviewed']);
    }

    public function test_product_show_returns_404_for_draft(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Draft]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")->assertNotFound();
    }

    public function test_product_show_includes_seo_fields(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'meta_title' => 'Custom SEO Title',
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.seo.meta_title', 'Custom SEO Title');
    }

    public function test_highlights_are_normalized_from_repeater_format(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'highlights' => [['item' => 'Point A'], ['item' => 'Point B']],
        ]);

        $response = $this->getJson("/api/v1/catalog/products/{$product->slug}");
        $highlights = $response->json('data.highlights');

        $this->assertIsArray($highlights);
        $this->assertSame(['Point A', 'Point B'], $highlights);
    }

    public function test_product_show_includes_description(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'description' => 'Full clinical detail about this product.',
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.description', 'Full clinical detail about this product.');
    }

    public function test_product_index_excludes_description(): void
    {
        Product::factory()->create(['status' => CatalogStatus::Published, 'description' => 'Should not appear']);

        $response = $this->getJson('/api/v1/catalog/products')->assertOk();
        $this->assertArrayNotHasKey('description', $response->json('data.0'));
    }

    public function test_per_page_is_capped_at_50(): void
    {
        Product::factory()->count(5)->create(['status' => CatalogStatus::Published]);

        $this->getJson('/api/v1/catalog/products?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }
}
