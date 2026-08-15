<?php

namespace Tests\Feature\Api\V1\Cms;

use App\Models\Blog\BlogPost;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MenuEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── GET /api/v1/menus ────────────────────────────────────────────

    public function test_index_lists_menus_with_name_and_slug(): void
    {
        Menu::factory()->create(['name' => 'Main navigation', 'slug' => 'main']);
        Menu::factory()->create(['name' => 'Footer', 'slug' => 'footer']);

        $this->getJson('/api/v1/menus')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => ['*' => ['name', 'slug', 'description']]]);
    }

    // ─── GET /api/v1/menus/{slug} ─────────────────────────────────────

    public function test_show_returns_nested_tree_in_position_order(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);
        $parent = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Products', 'url' => '#']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Child B']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'Child A']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Contact']);

        $items = $this->getJson('/api/v1/menus/main')->json('data.items');

        $this->assertSame(['Products', 'Contact'], array_column($items, 'label'));
        $this->assertSame(['Child B', 'Child A'], array_column($items[0]['children'], 'label'));
        $this->assertSame([], $items[1]['children']);
    }

    public function test_entity_links_resolve_current_slug_at_read_time(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);
        $page = Page::factory()->create(['slug' => 'contact-us']);
        $product = Product::factory()->create();

        MenuItem::factory()->linkedTo($page)->create(['menu_id' => $menu->id, 'label' => 'Contact']);
        MenuItem::factory()->linkedTo($product)->create(['menu_id' => $menu->id, 'label' => 'Shop', 'badge' => 'New']);

        $items = $this->getJson('/api/v1/menus/main')->json('data.items');

        $this->assertSame(['type' => 'page', 'slug' => 'contact-us'], $items[0]['link']);
        $this->assertSame('product', $items[1]['link']['type']);
        $this->assertSame($product->slug, $items[1]['link']['slug']);
        $this->assertSame('New', $items[1]['badge']);

        // Rename propagates without touching the menu.
        $page->update(['slug' => 'reach-us']);

        $items = $this->getJson('/api/v1/menus/main')->json('data.items');
        $this->assertSame('reach-us', $items[0]['link']['slug']);
    }

    public function test_items_with_unpublished_or_deleted_targets_are_dropped_with_children(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);

        $draftPage = Page::factory()->draft()->create();
        $deadParent = MenuItem::factory()->linkedTo($draftPage)->create(['menu_id' => $menu->id, 'label' => 'Hidden']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'parent_id' => $deadParent->id, 'label' => 'Orphan child']);

        $deletedProduct = Product::factory()->create();
        MenuItem::factory()->linkedTo($deletedProduct)->create(['menu_id' => $menu->id, 'label' => 'Gone']);
        $deletedProduct->forceDelete();

        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Alive', 'url' => '/still-here']);

        $items = $this->getJson('/api/v1/menus/main')->json('data.items');

        $this->assertSame(['Alive'], array_column($items, 'label'));
    }

    public function test_disabled_items_are_omitted(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Visible']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Hidden', 'enabled' => false]);

        $items = $this->getJson('/api/v1/menus/main')->json('data.items');

        $this->assertSame(['Visible'], array_column($items, 'label'));
    }

    public function test_anchor_and_url_links_emit_url(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);
        MenuItem::factory()->anchor('#pricing')->create(['menu_id' => $menu->id, 'label' => 'Pricing']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'External', 'url' => 'https://example.com', 'target' => '_blank']);

        $items = $this->getJson('/api/v1/menus/main')->json('data.items');

        $this->assertSame(['type' => 'anchor', 'url' => '#pricing'], $items[0]['link']);
        $this->assertSame('https://example.com', $items[1]['link']['url']);
        $this->assertSame('_blank', $items[1]['target']);
    }

    public function test_catalog_category_visibility_gates_menu_emission(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);
        $hidden = Category::factory()->create(['is_visible' => false]);
        MenuItem::factory()->linkedTo($hidden)->create(['menu_id' => $menu->id, 'label' => 'Hidden cat']);

        $this->assertSame([], $this->getJson('/api/v1/menus/main')->json('data.items'));
    }

    public function test_show_returns_404_for_unknown_menu(): void
    {
        $this->getJson('/api/v1/menus/nope')->assertStatus(404);
    }

    public function test_menu_edits_invalidate_cache_immediately(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'First']);

        $this->assertCount(1, $this->getJson('/api/v1/menus/main')->json('data.items'));

        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Second']);

        $this->assertCount(2, $this->getJson('/api/v1/menus/main')->json('data.items'));
    }

    public function test_blog_post_links_resolve_like_other_entities(): void
    {
        $menu = Menu::factory()->create(['slug' => 'main']);
        $post = BlogPost::factory()->create();
        MenuItem::factory()->linkedTo($post)->create(['menu_id' => $menu->id, 'label' => 'Read this']);

        $items = $this->getJson('/api/v1/menus/main')->json('data.items');

        if ($post->fresh()->isPublished()) {
            $this->assertSame('blog_post', $items[0]['link']['type']);
            $this->assertSame($post->slug, $items[0]['link']['slug']);
        } else {
            $this->assertSame([], $items);
        }
    }
}
