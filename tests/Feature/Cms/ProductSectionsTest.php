<?php

namespace Tests\Feature\Cms;

use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function pageWithSection(string $type, array $data, string $slug = 'test-page'): void
    {
        $page = Page::factory()->create(['slug' => $slug]);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => $type,
            'data' => $data,
        ]);
    }

    // ─── product-slider ───────────────────────────────────────────────

    public function test_manual_mode_inlines_products_preserving_admin_order(): void
    {
        $a = Product::factory()->create(['name' => 'Alpha']);
        $b = Product::factory()->create(['name' => 'Beta']);
        $c = Product::factory()->create(['name' => 'Gamma']);

        $this->pageWithSection('product-slider', [
            'mode' => 'manual',
            'product_ids' => [$c->id, $a->id, $b->id],
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertSame(
            ['Gamma', 'Alpha', 'Beta'],
            array_column($data['products'], 'name'),
        );
    }

    public function test_manual_mode_drops_unpublished_products(): void
    {
        $published = Product::factory()->create();
        $draft = Product::factory()->draft()->create();

        $this->pageWithSection('product-slider', [
            'mode' => 'manual',
            'product_ids' => [$draft->id, $published->id],
        ]);

        $products = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.products');

        $this->assertCount(1, $products);
        $this->assertSame($published->id, $products[0]['id']);
    }

    public function test_featured_mode_returns_only_featured_products(): void
    {
        Product::factory()->count(2)->create();
        $featured = Product::factory()->featured()->create();

        $this->pageWithSection('product-slider', [
            'mode' => 'featured',
            'limit' => 8,
        ]);

        $products = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.products');

        $this->assertCount(1, $products);
        $this->assertSame($featured->id, $products[0]['id']);
    }

    public function test_newest_mode_respects_limit(): void
    {
        Product::factory()->count(5)->create();

        $this->pageWithSection('product-slider', [
            'mode' => 'newest',
            'limit' => 3,
        ]);

        $products = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.products');

        $this->assertCount(3, $products);
    }

    public function test_category_mode_scopes_to_category(): void
    {
        $category = Category::factory()->create();
        $inCategory = Product::factory()->create();
        $inCategory->categories()->attach($category->id);
        Product::factory()->create();

        $this->pageWithSection('product-grid', [
            'mode' => 'category',
            'category_id' => $category->id,
            'limit' => 12,
        ]);

        $products = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.products');

        $this->assertCount(1, $products);
        $this->assertSame($inCategory->id, $products[0]['id']);
    }

    public function test_product_card_shape_includes_price_and_slug(): void
    {
        $product = Product::factory()->create(['retail_price' => 149]);

        $this->pageWithSection('product-slider', [
            'mode' => 'manual',
            'product_ids' => [$product->id],
        ]);

        $card = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.products.0');

        $this->assertArrayHasKey('slug', $card);
        $this->assertArrayHasKey('hero_image_url', $card);
        $this->assertSame(149.0, (float) $card['price']['retail']);
        $this->assertSame('USD', $card['price']['currency']);
    }

    // ─── product-callout ──────────────────────────────────────────────

    public function test_callout_inlines_single_product(): void
    {
        $product = Product::factory()->create();

        $this->pageWithSection('product-callout', [
            'item_type' => 'product',
            'product_id' => $product->id,
            'headline' => 'Custom headline',
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertSame($product->id, $data['product']['id']);
        $this->assertNull($data['package']);
        $this->assertSame('Custom headline', $data['headline']);
    }

    // ─── package-pricing-comparison ───────────────────────────────────

    public function test_comparison_inlines_packages_with_plans(): void
    {
        $packageA = Package::factory()->create();
        $packageB = Package::factory()->create();
        Plan::factory()->create(['package_id' => $packageA->id, 'retail_price' => 99]);

        $this->pageWithSection('package-pricing-comparison', [
            'package_ids' => [$packageB->id, $packageA->id],
        ]);

        $packages = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.packages');

        $this->assertSame([$packageB->id, $packageA->id], array_column($packages, 'id'));
        $this->assertCount(1, $packages[1]['plans']);
        $this->assertSame(99.0, (float) $packages[1]['plans'][0]['price']['retail']);
    }

    // ─── title banner ─────────────────────────────────────────────────

    public function test_title_banner_emitted_when_enabled_with_title_fallback(): void
    {
        Page::factory()->create([
            'slug' => 'banner-page',
            'title' => 'About Us',
            'title_banner' => [
                'enabled' => true,
                'subtitle' => 'Who we are',
                'show_breadcrumbs' => true,
            ],
        ]);

        $banner = $this->getJson('/api/v1/pages/banner-page')->json('data.title_banner');

        $this->assertSame('About Us', $banner['title']);
        $this->assertSame('Who we are', $banner['subtitle']);
        $this->assertTrue($banner['show_breadcrumbs']);
        $this->assertNull($banner['background_image']);
    }

    public function test_title_banner_null_when_disabled_or_absent(): void
    {
        Page::factory()->create(['slug' => 'no-banner', 'title_banner' => null]);
        Page::factory()->create([
            'slug' => 'disabled-banner',
            'title_banner' => ['enabled' => false, 'subtitle' => 'Hidden'],
        ]);

        $this->assertNull($this->getJson('/api/v1/pages/no-banner')->json('data.title_banner'));
        $this->assertNull($this->getJson('/api/v1/pages/disabled-banner')->json('data.title_banner'));
    }
}
