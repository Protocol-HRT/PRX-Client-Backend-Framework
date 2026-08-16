<?php

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Models\Catalog\CatalogItemSection;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Cms\GlobalSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_exposes_enabled_sections_as_envelopes_in_order(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        // Sortable's sort_when_creating assigns positions in creation order.
        CatalogItemSection::factory()
            ->type('text-block', ['heading' => 'About', 'body' => 'First band.'])
            ->for($product, 'sectionable')
            ->create(['anchor_id' => 'about']);
        CatalogItemSection::factory()
            ->type('video-embed', ['url' => 'https://example.com/demo.mp4'])
            ->for($product, 'sectionable')
            ->create();
        CatalogItemSection::factory()
            ->disabled()
            ->for($product, 'sectionable')
            ->create();

        $response = $this->getJson("/api/v1/catalog/products/{$product->slug}")->assertOk();

        $sections = $response->json('data.sections');
        $this->assertCount(2, $sections);
        $this->assertSame('text-block', $sections[0]['type']);
        $this->assertSame('code', $sections[0]['origin']);
        $this->assertSame('about', $sections[0]['anchor']);
        $this->assertNull($sections[0]['global']);
        $this->assertSame('First band.', $sections[0]['data']['body']);
        $this->assertSame('video-embed', $sections[1]['type']);
    }

    public function test_global_backed_section_renders_the_block_content(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        $global = GlobalSection::factory()->create([
            'type' => 'text-block',
            'data' => ['heading' => 'Why Peptides', 'body' => 'Shared copy.'],
            'enabled' => true,
        ]);

        CatalogItemSection::factory()
            ->type('text-block', [])
            ->for($product, 'sectionable')
            ->create(['global_section_id' => $global->id, 'data' => []]);

        $response = $this->getJson("/api/v1/catalog/products/{$product->slug}")->assertOk();

        $sections = $response->json('data.sections');
        $this->assertCount(1, $sections);
        $this->assertSame('Shared copy.', $sections[0]['data']['body']);
        $this->assertSame($global->slug, $sections[0]['global']['slug']);
    }

    public function test_unknown_section_type_is_skipped(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);
        CatalogItemSection::factory()
            ->type('no-such-type', ['x' => 1])
            ->for($product, 'sectionable')
            ->create();

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(0, 'data.sections');
    }

    public function test_package_show_exposes_sections(): void
    {
        $package = Package::factory()->create(['status' => CatalogStatus::Published]);
        CatalogItemSection::factory()
            ->type('text-block', ['heading' => 'Stack story', 'body' => 'Band.'])
            ->for($package, 'sectionable')
            ->create();

        $this->getJson("/api/v1/catalog/packages/{$package->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.sections')
            ->assertJsonPath('data.sections.0.type', 'text-block');
    }

    public function test_detail_layout_round_trips_on_show(): void
    {
        $product = Product::factory()->create([
            'status' => CatalogStatus::Published,
            'detail_layout' => [
                'template' => 'conversion',
                'accordions' => ['placement' => 'below'],
                'pair_with' => ['desktop' => 4, 'mobile' => 2],
                'rails' => ['related', 'stacks'],
            ],
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.detail_layout.template', 'conversion')
            ->assertJsonPath('data.detail_layout.accordions.placement', 'below')
            ->assertJsonPath('data.detail_layout.rails', ['related', 'stacks']);
    }

    public function test_detail_layout_is_null_by_default_and_absent_on_index(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.detail_layout', null);

        $index = $this->getJson('/api/v1/catalog/products')->assertOk();
        $this->assertArrayNotHasKey('detail_layout', $index->json('data.0'));
        $this->assertArrayNotHasKey('sections', $index->json('data.0'));
    }

    public function test_section_positions_scope_to_their_own_record(): void
    {
        $first = Product::factory()->create(['status' => CatalogStatus::Published]);
        $second = Product::factory()->create(['status' => CatalogStatus::Published]);

        $a = CatalogItemSection::factory()->for($first, 'sectionable')->create();
        $b = CatalogItemSection::factory()->for($second, 'sectionable')->create();

        $this->assertSame(1, $a->position);
        $this->assertSame(1, $b->position);
    }
}
