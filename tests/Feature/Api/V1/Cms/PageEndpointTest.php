<?php

namespace Tests\Feature\Api\V1\Cms;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PageEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── GET /api/v1/pages ────────────────────────────────────────────

    public function test_index_returns_only_published_pages(): void
    {
        Page::factory()->count(3)->create();
        Page::factory()->draft()->create();
        Page::factory()->archived()->create();

        $this->getJson('/api/v1/pages')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_response_shape(): void
    {
        Page::factory()->create(['title' => 'About Us', 'slug' => 'about']);

        $this->getJson('/api/v1/pages')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['title', 'slug', 'seo'],
                ],
            ])
            ->assertJsonPath('data.0.slug', 'about');
    }

    public function test_index_does_not_include_sections(): void
    {
        $page = Page::factory()->create();
        PageSection::factory()->hero()->create(['page_id' => $page->id, 'position' => 1]);

        $response = $this->getJson('/api/v1/pages');

        $data = $response->json('data.0');
        $this->assertArrayNotHasKey('sections', $data);
    }

    public function test_index_excludes_scheduled_unpublished_pages(): void
    {
        Page::factory()->create(['publish_at' => now()->addDay()]);

        $this->getJson('/api/v1/pages')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ─── GET /api/v1/pages/{slug} ─────────────────────────────────────

    public function test_show_returns_published_page_with_sections(): void
    {
        $page = Page::factory()->create(['slug' => 'home']);

        PageSection::factory()->hero()->create(['page_id' => $page->id, 'position' => 1]);

        $this->getJson('/api/v1/pages/home')
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'home')
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'slug',
                    'seo',
                    'sections' => [
                        '*' => ['type', 'data'],
                    ],
                ],
            ]);
    }

    public function test_show_sections_ordered_by_position(): void
    {
        $page = Page::factory()->create(['slug' => 'ordered']);

        // sort_when_creating assigns positions in creation order (1, 2, 3)
        // Create in the order we want them returned: text-block=1, faq=2, hero=3
        PageSection::factory()->create(['page_id' => $page->id, 'type' => 'text-block']);
        PageSection::factory()->create(['page_id' => $page->id, 'type' => 'faq']);
        PageSection::factory()->create(['page_id' => $page->id, 'type' => 'hero']);

        $sections = $this->getJson('/api/v1/pages/ordered')->json('data.sections');

        $this->assertSame('text-block', $sections[0]['type']);
        $this->assertSame('faq', $sections[1]['type']);
        $this->assertSame('hero', $sections[2]['type']);
    }

    public function test_show_seo_falls_back_to_title_when_meta_title_null(): void
    {
        Page::factory()->create([
            'slug' => 'no-meta',
            'title' => 'About Page',
            'meta_title' => null,
        ]);

        $this->getJson('/api/v1/pages/no-meta')
            ->assertStatus(200)
            ->assertJsonPath('data.seo.title', 'About Page');
    }

    public function test_show_returns_404_for_draft_page(): void
    {
        Page::factory()->draft()->create(['slug' => 'hidden-draft']);

        $this->getJson('/api/v1/pages/hidden-draft')
            ->assertStatus(404);
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/pages/does-not-exist')
            ->assertStatus(404);
    }

    public function test_show_includes_section_data_payload(): void
    {
        $page = Page::factory()->create(['slug' => 'with-data']);

        PageSection::factory()->hero()->create(['page_id' => $page->id, 'position' => 1]);

        $sections = $this->getJson('/api/v1/pages/with-data')->json('data.sections');

        $this->assertSame('hero', $sections[0]['type']);
        $this->assertArrayHasKey('headline', $sections[0]['data']);
    }

    // ─── Cache invalidation ───────────────────────────────────────────

    public function test_show_reflects_page_update_immediately(): void
    {
        $page = Page::factory()->create(['slug' => 'live-edit', 'title' => 'Before']);

        $this->getJson('/api/v1/pages/live-edit')
            ->assertJsonPath('data.title', 'Before');

        $page->update(['title' => 'After']);

        $this->getJson('/api/v1/pages/live-edit')
            ->assertJsonPath('data.title', 'After');
    }

    public function test_show_reflects_section_change_immediately(): void
    {
        $page = Page::factory()->create(['slug' => 'live-sections']);
        $this->getJson('/api/v1/pages/live-sections')
            ->assertJsonCount(0, 'data.sections');

        PageSection::factory()->hero()->create(['page_id' => $page->id, 'position' => 1]);

        $this->getJson('/api/v1/pages/live-sections')
            ->assertJsonCount(1, 'data.sections');
    }

    public function test_index_reflects_new_page_immediately(): void
    {
        Page::factory()->create(['slug' => 'first']);
        $this->getJson('/api/v1/pages')->assertJsonCount(1, 'data');

        Page::factory()->create(['slug' => 'second']);

        $this->getJson('/api/v1/pages')->assertJsonCount(2, 'data');
    }
}
