<?php

namespace Tests\Feature\Cms;

use App\Actions\Cms\CreateFlexibleSectionTypeAction;
use App\Actions\Cms\DeleteFlexibleSectionTypeAction;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Models\Catalog\Product;
use App\Models\Cms\FlexibleSectionType;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\SectionRegistry;
use App\Services\Cms\SvgSanitizer;
use Awcodes\Curator\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class FlexibleSectionTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        app(SectionRegistry::class)->flush();
    }

    private function makeType(array $fields, string $slug = 'trust-badges'): FlexibleSectionType
    {
        $type = FlexibleSectionType::factory()->create([
            'name' => 'Trust Badges',
            'slug' => $slug,
            'schema' => ['fields' => $fields],
        ]);

        app(SectionRegistry::class)->flush();

        return $type;
    }

    // ─── API emission ─────────────────────────────────────────────────

    public function test_flexible_section_emits_data_schema_map_and_origin(): void
    {
        $this->makeType([
            ['key' => 'heading', 'kind' => 'text', 'label' => 'Heading'],
            ['key' => 'badges', 'kind' => 'repeater', 'fields' => [
                ['key' => 'label', 'kind' => 'text'],
                ['key' => 'icon', 'kind' => 'image'],
            ]],
        ]);

        $page = Page::factory()->create(['slug' => 'flex-page']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'trust-badges',
            'data' => ['heading' => 'Why us', 'badges' => []],
        ]);

        $section = $this->getJson('/api/v1/pages/flex-page')->json('data.sections.0');

        $this->assertSame('trust-badges', $section['type']);
        $this->assertSame('flexible', $section['origin']);
        $this->assertSame('Why us', $section['data']['heading']);
        $this->assertSame('text', $section['schema']['heading']['kind']);
        $this->assertSame('repeater', $section['schema']['badges']['kind']);
        $this->assertSame('image', $section['schema']['badges']['fields']['icon']['kind']);
    }

    public function test_flexible_image_and_repeater_image_fields_resolve_media(): void
    {
        $media = Media::query()->create([
            'disk' => 'public', 'directory' => 'media', 'visibility' => 'public',
            'name' => 'badge', 'path' => 'media/badge.png', 'width' => 64, 'height' => 64,
            'size' => 500, 'type' => 'image/png', 'ext' => 'png', 'alt' => 'Badge',
        ]);

        $this->makeType([
            ['key' => 'hero_image', 'kind' => 'image'],
            ['key' => 'items', 'kind' => 'repeater', 'fields' => [
                ['key' => 'icon', 'kind' => 'image'],
            ]],
        ]);

        $page = Page::factory()->create(['slug' => 'flex-media']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'trust-badges',
            'data' => [
                'hero_image' => $media->id,
                'items' => [['icon' => $media->id]],
            ],
        ]);

        $data = $this->getJson('/api/v1/pages/flex-media')->json('data.sections.0.data');

        $this->assertSame($media->id, $data['hero_image']['id']);
        $this->assertSame('Badge', $data['items'][0]['icon']['alt']);
    }

    public function test_flexible_products_field_inlines_published_products(): void
    {
        $product = Product::factory()->create();
        $draft = Product::factory()->draft()->create();

        $this->makeType([
            ['key' => 'picks', 'kind' => 'products'],
        ]);

        $page = Page::factory()->create(['slug' => 'flex-products']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'trust-badges',
            'data' => ['picks' => [$product->id, $draft->id]],
        ]);

        $picks = $this->getJson('/api/v1/pages/flex-products')->json('data.sections.0.data.picks');

        $this->assertCount(1, $picks);
        $this->assertSame($product->id, $picks[0]['id']);
    }

    public function test_svg_fields_are_sanitized_in_api_output(): void
    {
        $this->makeType([
            ['key' => 'icon_svg', 'kind' => 'svg'],
        ]);

        $page = Page::factory()->create(['slug' => 'flex-svg']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'trust-badges',
            'data' => ['icon_svg' => '<svg viewBox="0 0 1 1" onclick="alert(1)"><script>alert(2)</script><path d="M0 0"/></svg>'],
        ]);

        $svg = $this->getJson('/api/v1/pages/flex-svg')->json('data.sections.0.data.icon_svg');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<path', $svg);
        $this->assertStringNotContainsString('script', $svg);
        $this->assertStringNotContainsString('onclick', $svg);
    }

    public function test_sections_of_disabled_flexible_type_are_skipped(): void
    {
        $type = $this->makeType([['key' => 'heading', 'kind' => 'text']]);

        $page = Page::factory()->create(['slug' => 'flex-disabled']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'trust-badges',
            'data' => ['heading' => 'Hi'],
        ]);
        PageSection::factory()->hero()->create(['page_id' => $page->id]);

        $type->update(['enabled' => false]);
        app(SectionRegistry::class)->flush();

        $sections = $this->getJson('/api/v1/pages/flex-disabled')->json('data.sections');

        $this->assertCount(1, $sections);
        $this->assertSame('hero', $sections[0]['type']);
    }

    // ─── Registry ─────────────────────────────────────────────────────

    public function test_code_definition_wins_on_slug_collision(): void
    {
        FlexibleSectionType::factory()->create(['slug' => 'hero']);
        app(SectionRegistry::class)->flush();

        $definition = app(SectionRegistry::class)->resolve('hero');

        $this->assertFalse($definition->isFlexible());
    }

    // ─── Authoring actions ────────────────────────────────────────────

    public function test_reserved_slug_is_rejected_on_create(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CreateFlexibleSectionTypeAction::class)->execute(
            FlexibleSectionTypeData::validateAndCreate([
                'name' => 'Fake Hero',
                'slug' => 'hero',
                'schema' => ['fields' => [['key' => 'heading', 'kind' => 'text']]],
            ]),
        );
    }

    public function test_schema_with_nested_repeater_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CreateFlexibleSectionTypeAction::class)->execute(
            FlexibleSectionTypeData::validateAndCreate([
                'name' => 'Bad',
                'slug' => 'bad-nesting',
                'schema' => ['fields' => [
                    ['key' => 'items', 'kind' => 'repeater', 'fields' => [
                        ['key' => 'nested', 'kind' => 'repeater', 'fields' => [
                            ['key' => 'deep', 'kind' => 'text'],
                        ]],
                    ]],
                ]],
            ]),
        );
    }

    public function test_schema_with_duplicate_keys_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CreateFlexibleSectionTypeAction::class)->execute(
            FlexibleSectionTypeData::validateAndCreate([
                'name' => 'Bad',
                'slug' => 'bad-dupes',
                'schema' => ['fields' => [
                    ['key' => 'heading', 'kind' => 'text'],
                    ['key' => 'heading', 'kind' => 'textarea'],
                ]],
            ]),
        );
    }

    public function test_delete_blocked_while_type_is_in_use(): void
    {
        $type = $this->makeType([['key' => 'heading', 'kind' => 'text']]);
        PageSection::factory()->create(['type' => 'trust-badges']);

        $this->expectException(RuntimeException::class);

        app(DeleteFlexibleSectionTypeAction::class)->execute($type);
    }

    // ─── SvgSanitizer ─────────────────────────────────────────────────

    public function test_svg_sanitizer_rejects_non_svg_markup(): void
    {
        $this->assertNull(SvgSanitizer::sanitize('<div>not svg</div>'));
        $this->assertNull(SvgSanitizer::sanitize(''));
        $this->assertNull(SvgSanitizer::sanitize('<script>alert(1)</script>'));
    }

    public function test_svg_sanitizer_strips_javascript_urls(): void
    {
        $clean = SvgSanitizer::sanitize('<svg><a href="javascript:alert(1)"><text>x</text></a></svg>');

        $this->assertStringNotContainsString('javascript', $clean);
    }
}
