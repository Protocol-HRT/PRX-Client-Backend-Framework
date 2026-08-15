<?php

namespace Tests\Feature\Api\V1\Cms;

use App\Enums\Cms\Region;
use App\Models\Cms\GlobalSection;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\RegionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LayoutEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_all_region_keys_are_always_present(): void
    {
        $regions = $this->getJson('/api/v1/layout')->assertStatus(200)->json('data.regions');

        $this->assertSame(
            ['top_bar', 'header', 'pre_footer', 'footer', 'sidebar_left', 'sidebar_right'],
            array_keys($regions),
        );
        $this->assertSame([], $regions['header']);
    }

    public function test_section_items_emit_the_standard_envelope(): void
    {
        RegionItem::factory()->inRegion(Region::TopBar)->create([
            'data' => ['heading' => 'Free shipping'],
        ]);

        $item = $this->getJson('/api/v1/layout')->json('data.regions.top_bar.0');

        $this->assertSame('section', $item['kind']);
        $this->assertSame('cta-banner', $item['section']['type']);
        $this->assertSame('code', $item['section']['origin']);
        $this->assertSame('Free shipping', $item['section']['data']['heading']);
    }

    public function test_menu_items_embed_the_resolved_tree(): void
    {
        $menu = Menu::factory()->create(['name' => 'Main', 'slug' => 'main']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Home', 'url' => '/']);
        RegionItem::factory()->inRegion(Region::Header)->forMenu($menu)->create();

        $item = $this->getJson('/api/v1/layout')->json('data.regions.header.0');

        $this->assertSame('menu', $item['kind']);
        $this->assertSame('main', $item['menu']['slug']);
        $this->assertSame('Home', $item['menu']['items'][0]['label']);
    }

    public function test_global_block_items_resolve_with_global_reference(): void
    {
        $global = GlobalSection::factory()->create([
            'slug' => 'footer-cta',
            'data' => ['heading' => 'Ready?'],
        ]);
        RegionItem::factory()->inRegion(Region::Footer)->forGlobalSection($global)->create();

        $item = $this->getJson('/api/v1/layout')->json('data.regions.footer.0');

        $this->assertSame('section', $item['kind']);
        $this->assertSame('footer-cta', $item['section']['global']['slug']);
        $this->assertSame('Ready?', $item['section']['data']['heading']);
    }

    public function test_disabled_items_and_disabled_globals_are_dropped(): void
    {
        RegionItem::factory()->inRegion(Region::Header)->create(['enabled' => false]);
        $disabledGlobal = GlobalSection::factory()->disabled()->create();
        RegionItem::factory()->inRegion(Region::Header)->forGlobalSection($disabledGlobal)->create();
        RegionItem::factory()->inRegion(Region::Header)->create(['data' => ['heading' => 'Visible']]);

        $header = $this->getJson('/api/v1/layout')->json('data.regions.header');

        $this->assertCount(1, $header);
        $this->assertSame('Visible', $header[0]['section']['data']['heading']);
    }

    public function test_items_are_ordered_by_position_within_a_region(): void
    {
        RegionItem::factory()->inRegion(Region::Footer)->create(['data' => ['heading' => 'First']]);
        RegionItem::factory()->inRegion(Region::Footer)->create(['data' => ['heading' => 'Second']]);

        $footer = $this->getJson('/api/v1/layout')->json('data.regions.footer');

        $this->assertSame(['First', 'Second'], array_column(array_column(array_column($footer, 'section'), 'data'), 'heading'));
    }

    public function test_layout_edits_invalidate_cache_immediately(): void
    {
        $this->assertSame([], $this->getJson('/api/v1/layout')->json('data.regions.top_bar'));

        RegionItem::factory()->inRegion(Region::TopBar)->create();

        $this->assertCount(1, $this->getJson('/api/v1/layout')->json('data.regions.top_bar'));
    }
}
