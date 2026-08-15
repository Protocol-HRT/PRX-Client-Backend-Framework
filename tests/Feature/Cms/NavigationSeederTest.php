<?php

namespace Tests\Feature\Cms;

use App\Enums\PageStatus;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\RegionItem;
use App\Models\Page;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NavigationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_seeds_menus_region_mounts_and_target_pages(): void
    {
        $this->seed(NavigationSeeder::class);

        $this->assertSame(3, Menu::count());
        $this->assertSame(10, MenuItem::count());
        $this->assertSame(3, RegionItem::count());

        foreach (['about-us', 'how-it-works', 'faq', 'contact-us', 'privacy-policy', 'terms-and-conditions'] as $slug) {
            $page = Page::where('slug', $slug)->first();
            $this->assertNotNull($page, "Missing seeded page {$slug}");
            $this->assertTrue($page->isPublished(), "Page {$slug} not published");
        }
    }

    public function test_layout_endpoint_serves_seeded_chrome(): void
    {
        $this->seed(NavigationSeeder::class);

        $regions = $this->getJson('/api/v1/layout')
            ->assertOk()
            ->json('data.regions');

        $this->assertSame('main-nav', $regions['header'][0]['menu']['slug']);
        $this->assertCount(5, $regions['header'][0]['menu']['items']);
        $this->assertSame(
            ['footer-quick-links', 'footer-legal'],
            array_column(array_column($regions['footer'], 'menu'), 'slug'),
        );
    }

    public function test_reseeding_is_idempotent_and_preserves_existing_pages(): void
    {
        $existing = Page::factory()->create([
            'slug' => 'about-us',
            'title' => 'Our Real About Copy',
            'status' => PageStatus::Published,
        ]);

        $this->seed(NavigationSeeder::class);
        $this->seed(NavigationSeeder::class);

        $this->assertSame(3, Menu::count());
        $this->assertSame(10, MenuItem::count());
        $this->assertSame(3, RegionItem::count());
        $this->assertSame('Our Real About Copy', $existing->fresh()->title);
        $this->assertSame(1, Page::where('slug', 'about-us')->count());
    }
}
