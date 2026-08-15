<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\MediaResolver;
use Awcodes\Curator\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SectionMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeMedia(array $overrides = []): Media
    {
        return Media::query()->create(array_merge([
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'name' => 'test-image',
            'path' => 'media/test-image.jpg',
            'width' => 800,
            'height' => 600,
            'size' => 1024,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'alt' => 'A test image',
        ], $overrides));
    }

    // ─── MediaResolver ────────────────────────────────────────────────

    public function test_resolver_resolves_media_id_to_url_object(): void
    {
        $media = $this->makeMedia();

        $resolved = app(MediaResolver::class)->resolve($media->id);

        $this->assertSame($media->id, $resolved['id']);
        $this->assertStringContainsString('media/test-image.jpg', $resolved['url']);
        $this->assertSame('A test image', $resolved['alt']);
        $this->assertSame(800, $resolved['width']);
        $this->assertSame(600, $resolved['height']);
    }

    public function test_resolver_dual_reads_legacy_path_strings(): void
    {
        $resolved = app(MediaResolver::class)->resolve('brand/hero/legacy.jpg');

        $this->assertNull($resolved['id']);
        $this->assertStringContainsString('brand/hero/legacy.jpg', $resolved['url']);
        $this->assertNull($resolved['alt']);
    }

    public function test_resolver_returns_null_for_empty_and_missing(): void
    {
        $resolver = app(MediaResolver::class);

        $this->assertNull($resolver->resolve(null));
        $this->assertNull($resolver->resolve(''));
        $this->assertNull($resolver->resolve(999999));
    }

    // ─── API payload transformation ───────────────────────────────────

    public function test_api_resolves_top_level_image_field_to_media_object(): void
    {
        $media = $this->makeMedia();
        $page = Page::factory()->create(['slug' => 'media-page']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'image-text-split',
            'data' => ['heading' => 'Split', 'image' => $media->id],
        ]);

        $section = $this->getJson('/api/v1/pages/media-page')->json('data.sections.0');

        $this->assertSame($media->id, $section['data']['image']['id']);
        $this->assertStringContainsString('media/test-image.jpg', $section['data']['image']['url']);
        $this->assertSame('A test image', $section['data']['image']['alt']);
    }

    public function test_api_resolves_repeater_image_fields(): void
    {
        $media = $this->makeMedia();
        $page = Page::factory()->create(['slug' => 'repeater-page']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'testimonials',
            'data' => [
                'quotes' => [
                    ['name' => 'A', 'image' => $media->id],
                    ['name' => 'B', 'image' => 'legacy/headshot.jpg'],
                    ['name' => 'C', 'image' => null],
                ],
            ],
        ]);

        $quotes = $this->getJson('/api/v1/pages/repeater-page')->json('data.sections.0.data.quotes');

        $this->assertSame($media->id, $quotes[0]['image']['id']);
        $this->assertNull($quotes[1]['image']['id']);
        $this->assertStringContainsString('legacy/headshot.jpg', $quotes[1]['image']['url']);
        $this->assertNull($quotes[2]['image']);
    }

    public function test_api_emits_section_envelope_with_origin_and_anchor(): void
    {
        $page = Page::factory()->create(['slug' => 'envelope']);
        PageSection::factory()->hero()->create([
            'page_id' => $page->id,
            'anchor_id' => 'top',
        ]);

        $section = $this->getJson('/api/v1/pages/envelope')->json('data.sections.0');

        $this->assertSame('hero', $section['type']);
        $this->assertSame('code', $section['origin']);
        $this->assertSame('top', $section['anchor']);
        $this->assertNull($section['global']);
    }

    public function test_api_skips_disabled_sections(): void
    {
        $page = Page::factory()->create(['slug' => 'with-disabled']);
        PageSection::factory()->hero()->create(['page_id' => $page->id, 'enabled' => true]);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'faq',
            'enabled' => false,
        ]);

        $sections = $this->getJson('/api/v1/pages/with-disabled')->json('data.sections');

        $this->assertCount(1, $sections);
        $this->assertSame('hero', $sections[0]['type']);
    }

    public function test_api_skips_sections_with_unknown_type(): void
    {
        $page = Page::factory()->create(['slug' => 'unknown-type']);
        PageSection::factory()->hero()->create(['page_id' => $page->id]);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'deleted-flexible-type',
        ]);

        $sections = $this->getJson('/api/v1/pages/unknown-type')->json('data.sections');

        $this->assertCount(1, $sections);
    }

    // ─── cms:backfill-section-media ───────────────────────────────────

    public function test_backfill_converts_path_matching_existing_media_row(): void
    {
        $media = $this->makeMedia(['path' => 'brand/photo.jpg']);
        $section = PageSection::factory()->create([
            'type' => 'image-text-split',
            'data' => ['image' => '/storage/brand/photo.jpg'],
        ]);

        $this->artisan('cms:backfill-section-media')
            ->expectsOutputToContain('1 image value(s) converted')
            ->assertSuccessful();

        $this->assertSame($media->id, $section->fresh()->data['image']);
    }

    public function test_backfill_creates_media_row_for_orphan_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'brand/orphan.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        );

        $section = PageSection::factory()->create([
            'type' => 'cta-banner',
            'data' => ['background_image' => 'brand/orphan.png'],
        ]);

        $this->artisan('cms:backfill-section-media')->assertSuccessful();

        $imageValue = $section->fresh()->data['background_image'];
        $this->assertIsInt($imageValue);
        $this->assertSame('brand/orphan.png', Media::query()->find($imageValue)->path);
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $this->makeMedia(['path' => 'brand/photo.jpg']);
        $section = PageSection::factory()->create([
            'type' => 'image-text-split',
            'data' => ['image' => 'brand/photo.jpg'],
        ]);

        $this->artisan('cms:backfill-section-media', ['--dry-run' => true])
            ->expectsOutputToContain('would be converted')
            ->assertSuccessful();

        $this->assertSame('brand/photo.jpg', $section->fresh()->data['image']);
    }

    public function test_backfill_leaves_unresolvable_paths_untouched(): void
    {
        Storage::fake('public');

        $section = PageSection::factory()->create([
            'type' => 'image-text-split',
            'data' => ['image' => 'missing/nowhere.jpg'],
        ]);

        $this->artisan('cms:backfill-section-media')
            ->expectsOutputToContain('Unresolved')
            ->assertSuccessful();

        $this->assertSame('missing/nowhere.jpg', $section->fresh()->data['image']);
    }
}
