<?php

namespace Tests\Feature\Cms;

use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\SectionRegistry;
use Awcodes\Curator\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The Figma-derived conversion-page blueprints: highlight-banner,
 * benefits-diagram, image-callout-banner.
 */
class ConversionSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function pageWithSection(string $type, array $data): void
    {
        $page = Page::factory()->create(['slug' => 'test-page']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => $type,
            'data' => $data,
        ]);
    }

    private function makeMedia(string $path = 'media/icon.png'): Media
    {
        return Media::query()->create([
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => 'icon',
            'path' => $path,
            'width' => 64,
            'height' => 64,
            'size' => 512,
            'type' => 'image/png',
            'ext' => 'png',
        ]);
    }

    public function test_new_types_are_registered(): void
    {
        $options = app(SectionRegistry::class)->options();

        $this->assertArrayHasKey('highlight-banner', $options);
        $this->assertArrayHasKey('benefits-diagram', $options);
        $this->assertArrayHasKey('image-callout-banner', $options);
    }

    // ─── highlight-banner ─────────────────────────────────────────────

    public function test_highlight_banner_resolves_item_icons_and_keeps_layout_fields(): void
    {
        $media = $this->makeMedia();

        $this->pageWithSection('highlight-banner', [
            'items' => [
                ['icon' => $media->id, 'text' => "Designed by\nLeading Physicians"],
                ['icon' => null, 'text' => 'Licensed Prescribers'],
            ],
            'icon_placement' => 'top',
            'per_row' => '2',
            'bordered' => true,
            'theme' => 'cream',
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertStringContainsString('media/icon.png', $data['items'][0]['icon']['url']);
        $this->assertNull($data['items'][1]['icon']);
        $this->assertSame('top', $data['icon_placement']);
        $this->assertSame('2', $data['per_row']);
        $this->assertTrue($data['bordered']);
    }

    // ─── benefits-diagram ─────────────────────────────────────────────

    public function test_benefits_diagram_add_to_cart_cta_inlines_product(): void
    {
        $product = Product::factory()->create(['retail_price' => 149]);

        $this->pageWithSection('benefits-diagram', [
            'heading' => 'Benefits',
            'points' => [['text' => 'Enhanced Satiety', 'side' => 'left', 'icon' => null]],
            'cta_label' => 'Start Your Wellness Journey',
            'cta_mode' => 'add_to_cart',
            'cta_item_type' => 'product',
            'cta_product_id' => $product->id,
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertSame($product->id, $data['cta_product']['id']);
        $this->assertSame(149.0, (float) $data['cta_product']['price']['retail']);
        $this->assertNull($data['cta_package']);
    }

    public function test_benefits_diagram_link_cta_inlines_nothing(): void
    {
        $product = Product::factory()->create();

        $this->pageWithSection('benefits-diagram', [
            'points' => [['text' => 'Metabolic Health', 'side' => 'right', 'icon' => null]],
            'cta_mode' => 'link',
            'cta_url' => '/products',
            'cta_product_id' => $product->id,
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertNull($data['cta_product']);
        $this->assertNull($data['cta_package']);
        $this->assertSame('/products', $data['cta_url']);
    }

    public function test_benefits_diagram_add_to_cart_cta_inlines_package_with_plans(): void
    {
        $package = Package::factory()->create();
        Plan::factory()->create(['package_id' => $package->id, 'retail_price' => 279.99]);

        $this->pageWithSection('benefits-diagram', [
            'points' => [],
            'cta_mode' => 'add_to_cart',
            'cta_item_type' => 'package',
            'cta_package_id' => $package->id,
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertSame($package->id, $data['cta_package']['id']);
        $this->assertSame(279.99, (float) $data['cta_package']['plans'][0]['price']['retail']);
        $this->assertNull($data['cta_product']);
    }

    // ─── image-callout-banner ─────────────────────────────────────────

    public function test_image_callout_banner_resolves_background_and_per_callout_ctas(): void
    {
        $media = $this->makeMedia('media/bg.jpg');
        $product = Product::factory()->create();

        $this->pageWithSection('image-callout-banner', [
            'background_image' => $media->id,
            'background_alt' => 'Lifestyle shot',
            'callouts' => [
                [
                    'position' => '0',
                    'color' => null,
                    'icon' => null,
                    'title' => 'Left card',
                    'content' => 'Copy.',
                    'cta_mode' => 'add_to_cart',
                    'cta_item_type' => 'product',
                    'cta_product_id' => $product->id,
                ],
                [
                    'position' => '1',
                    'color' => '#f0e6da',
                    'icon' => null,
                    'title' => 'Right card',
                    'content' => 'Copy.',
                    'cta_mode' => 'link',
                    'cta_url' => '/stacks',
                ],
            ],
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertStringContainsString('media/bg.jpg', $data['background_image']['url']);
        $this->assertSame($product->id, $data['callouts'][0]['cta_product']['id']);
        $this->assertNull($data['callouts'][1]['cta_product']);
        $this->assertSame('#f0e6da', $data['callouts'][1]['color']);
        $this->assertSame('/stacks', $data['callouts'][1]['cta_url']);
    }

    public function test_image_callout_banner_drops_unpublished_cta_product(): void
    {
        $draft = Product::factory()->draft()->create();

        $this->pageWithSection('image-callout-banner', [
            'background_image' => null,
            'callouts' => [[
                'position' => '0',
                'title' => 'Card',
                'cta_mode' => 'add_to_cart',
                'cta_item_type' => 'product',
                'cta_product_id' => $draft->id,
            ]],
        ]);

        $data = $this->getJson('/api/v1/pages/test-page')->json('data.sections.0.data');

        $this->assertNull($data['callouts'][0]['cta_product']);
    }
}
