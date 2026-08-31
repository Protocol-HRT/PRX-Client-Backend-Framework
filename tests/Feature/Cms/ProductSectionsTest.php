<?php

namespace Tests\Feature\Cms;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\CatalogInliner;
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

    // ─── package-slider ───────────────────────────────────────────────

    public function test_package_slider_manual_mode_inlines_packages_preserving_admin_order(): void
    {
        $a = Package::factory()->create(['name' => 'Alpha Stack']);
        $b = Package::factory()->create(['name' => 'Beta Stack']);
        $draft = Package::factory()->draft()->create();

        $this->pageWithSection('package-slider', [
            'mode' => 'manual',
            'package_ids' => [$b->id, $draft->id, $a->id],
        ]);

        $packages = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.packages');

        $this->assertSame(
            ['Beta Stack', 'Alpha Stack'],
            array_column($packages, 'name'),
        );
    }

    public function test_package_slider_featured_mode_returns_only_featured_packages(): void
    {
        Package::factory()->count(2)->create();
        $featured = Package::factory()->featured()->create();

        $this->pageWithSection('package-slider', [
            'mode' => 'featured',
            'limit' => 8,
        ]);

        $packages = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.packages');

        $this->assertCount(1, $packages);
        $this->assertSame($featured->id, $packages[0]['id']);
    }

    /**
     * A package slider on a CMS page embeds each package's products. That load
     * was unconstrained, while Package::products() carries no status filter of
     * its own — so a draft product attached to a published stack had its name,
     * slug and price served to any visitor of the home page. It was invisible
     * because the catalog happened to be entirely published.
     */
    public function test_package_slider_does_not_inline_unpublished_products(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        $live = Product::factory()->create(['status' => CatalogStatus::Published, 'name' => 'On Sale']);
        $draft = Product::factory()->create(['status' => CatalogStatus::Draft, 'name' => 'Not Announced Yet']);
        $package->products()->attach([$live->id, $draft->id]);

        $inlined = app(CatalogInliner::class)->packagesByIds([$package->id]);

        $names = array_column($inlined[0]['products'], 'name');
        $this->assertSame(['On Sale'], $names);
    }

    public function test_package_slider_inlines_plan_pricing_with_intro_price(): void
    {
        $package = Package::factory()->create();
        Plan::factory()->create([
            'package_id' => $package->id,
            'retail_price' => 279.99,
            'sale_price' => null,
            'intro_price' => 179.99,
        ]);

        $this->pageWithSection('package-slider', [
            'mode' => 'manual',
            'package_ids' => [$package->id],
        ]);

        $price = $this->getJson('/api/v1/pages/test-page')
            ->json('data.sections.0.data.packages.0.plans.0.price');

        $this->assertSame(279.99, (float) $price['retail']);
        $this->assertSame(179.99, (float) $price['intro']);
        $this->assertSame(279.99, (float) $price['effective']);
    }

    // ─── category-grid ────────────────────────────────────────────────

    public function test_category_grid_all_mode_lists_visible_categories_only(): void
    {
        $visible = Category::factory()->create(['is_visible' => true, 'name' => 'Anti-Aging']);
        Category::factory()->create(['is_visible' => false]);

        $this->pageWithSection('category-grid', [
            'mode' => 'all',
            'limit' => 12,
        ]);

        $categories = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.categories');

        $this->assertCount(1, $categories);
        $this->assertSame($visible->id, $categories[0]['id']);
        $this->assertArrayHasKey('slug', $categories[0]);
        $this->assertArrayHasKey('hero_image_url', $categories[0]);
    }

    public function test_category_grid_manual_mode_preserves_order_and_drops_hidden(): void
    {
        $a = Category::factory()->create(['is_visible' => true, 'name' => 'Healing']);
        $b = Category::factory()->create(['is_visible' => true, 'name' => 'Longevity']);
        $hidden = Category::factory()->create(['is_visible' => false]);

        $this->pageWithSection('category-grid', [
            'mode' => 'manual',
            'category_ids' => [$b->id, $hidden->id, $a->id],
        ]);

        $categories = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data.categories');

        $this->assertSame(['Longevity', 'Healing'], array_column($categories, 'name'));
    }

    public function test_inlined_payloads_survive_cache_serialization(): void
    {
        $package = Package::factory()->create();
        Plan::factory()->create(['package_id' => $package->id]);
        $product = Product::factory()->create();

        $inliner = app(CatalogInliner::class);

        // CmsCache serializes section payloads; nested resource objects would
        // come back as __PHP_Incomplete_Class. allowed_classes:false replicates
        // the strictest round-trip: only plain data may remain.
        foreach ([
            $inliner->packagesByIds([$package->id]),
            $inliner->productsByIds([$product->id]),
        ] as $payload) {
            $restored = unserialize(serialize($payload), ['allowed_classes' => false]);
            array_walk_recursive($restored, fn ($value) => $this->assertIsNotObject($value));
        }

        $restoredPackage = unserialize(serialize($inliner->packagesByIds([$package->id])), ['allowed_classes' => false])[0];
        $this->assertIsList($restoredPackage['plans']);
        $this->assertIsArray($restoredPackage['plans'][0]['price']);
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
