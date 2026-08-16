<?php

namespace Tests\Feature\Cms;

use App\Actions\Cms\UpdateFlexibleSectionTypeAction;
use App\Cms\Support\VisibleWhen;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Cms\FlexibleSectionType;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\SectionRegistry;
use App\Services\Cms\SectionResolverOps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Coverage for the data-driven section vocabulary added for the
 * code-to-data migration: single catalog picker kinds, the CTA group kind,
 * visible_when rules, automatic select string-casting, and the declarative
 * resolver pipeline.
 */
class SectionPlatformVocabularyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        app(SectionRegistry::class)->flush();
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<string, mixed>>  $resolvers
     */
    private function makeType(array $fields, array $resolvers = [], string $slug = 'vocab-demo'): FlexibleSectionType
    {
        $schema = ['fields' => $fields];

        if ($resolvers !== []) {
            $schema['resolvers'] = $resolvers;
        }

        $type = FlexibleSectionType::factory()->create([
            'name' => 'Vocab Demo',
            'slug' => $slug,
            'schema' => $schema,
        ]);

        app(SectionRegistry::class)->flush();

        return $type;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sectionPayload(array $data, string $slug = 'vocab-demo'): array
    {
        $page = Page::factory()->create(['slug' => 'vocab-page']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => $slug,
            'data' => $data,
        ]);

        return $this->getJson('/api/v1/pages/vocab-page')->json('data.sections.0');
    }

    // ─── New field kinds ──────────────────────────────────────────────

    public function test_single_product_and_package_kinds_inline_catalog_cards(): void
    {
        $product = Product::factory()->create();
        $package = Package::factory()->create();

        $this->makeType([
            ['key' => 'featured_product', 'kind' => 'product'],
            ['key' => 'featured_package', 'kind' => 'package'],
        ]);

        $data = $this->sectionPayload([
            'featured_product' => $product->id,
            'featured_package' => $package->id,
        ])['data'];

        $this->assertSame($product->name, $data['featured_product']['name']);
        $this->assertSame($package->name, $data['featured_package']['name']);
    }

    public function test_raw_flag_keeps_picker_value_unresolved(): void
    {
        $product = Product::factory()->create();

        $this->makeType(
            [['key' => 'product_ids', 'kind' => 'products', 'raw' => true]],
            [['op' => 'inline_products', 'input' => 'product_ids', 'output' => 'products']],
        );

        $section = $this->sectionPayload(['product_ids' => [$product->id]]);

        $this->assertSame([$product->id], $section['data']['product_ids']);
        $this->assertSame($product->name, $section['data']['products'][0]['name']);
        $this->assertTrue($section['schema']['product_ids']['raw']);
    }

    public function test_cta_kind_resolves_add_to_cart_target(): void
    {
        $product = Product::factory()->create();

        $this->makeType([
            ['key' => 'heading', 'kind' => 'text'],
            ['key' => 'cta', 'kind' => 'cta'],
        ]);

        $data = $this->sectionPayload([
            'heading' => 'Buy now',
            'cta_label' => 'Add to cart',
            'cta_mode' => 'add_to_cart',
            'cta_item_type' => 'product',
            'cta_product_id' => $product->id,
        ])['data'];

        $this->assertSame($product->name, $data['cta_product']['name']);
        $this->assertNull($data['cta_package']);
        $this->assertSame($product->id, $data['cta_product_id']);
    }

    public function test_cta_kind_in_link_mode_inlines_nothing(): void
    {
        $this->makeType([['key' => 'cta', 'kind' => 'cta']]);

        $data = $this->sectionPayload([
            'cta_label' => 'Learn more',
            'cta_mode' => 'link',
            'cta_url' => '/about',
        ])['data'];

        $this->assertNull($data['cta_product']);
        $this->assertNull($data['cta_package']);
    }

    public function test_cta_kind_resolves_inside_repeater_items(): void
    {
        $product = Product::factory()->create();

        $this->makeType([
            ['key' => 'cards', 'kind' => 'repeater', 'fields' => [
                ['key' => 'title', 'kind' => 'text'],
                ['key' => 'cta', 'kind' => 'cta'],
            ]],
        ]);

        $data = $this->sectionPayload([
            'cards' => [
                ['title' => 'One', 'cta_mode' => 'add_to_cart', 'cta_item_type' => 'product', 'cta_product_id' => $product->id],
                ['title' => 'Two', 'cta_mode' => 'link', 'cta_url' => '/x'],
            ],
        ])['data'];

        $this->assertSame($product->name, $data['cards'][0]['cta_product']['name']);
        $this->assertNull($data['cards'][1]['cta_product']);
    }

    public function test_select_values_are_cast_to_string_in_payload(): void
    {
        $this->makeType([
            ['key' => 'position', 'kind' => 'select', 'options' => [
                ['value' => '0', 'label' => 'Left'],
                ['value' => '1', 'label' => 'Right'],
            ]],
            ['key' => 'slots', 'kind' => 'repeater', 'fields' => [
                ['key' => 'slot', 'kind' => 'select', 'options' => [['value' => '0', 'label' => 'First']]],
            ]],
        ]);

        // Filament re-saves select state as integers — the payload contract
        // is the authored string values.
        $data = $this->sectionPayload([
            'position' => 1,
            'slots' => [['slot' => 0]],
        ])['data'];

        $this->assertSame('1', $data['position']);
        $this->assertSame('0', $data['slots'][0]['slot']);
    }

    // ─── visible_when ─────────────────────────────────────────────────

    public function test_visible_when_conditions_evaluate_with_loose_comparison(): void
    {
        $get = fn (array $state) => fn (string $key) => $state[$key] ?? null;

        $equals = [['field' => 'mode', 'operator' => 'equals', 'value' => 'manual']];
        $this->assertTrue(VisibleWhen::passes($equals, $get(['mode' => 'manual'])));
        $this->assertFalse(VisibleWhen::passes($equals, $get(['mode' => 'featured'])));

        // Loose: Filament re-saves '4' as 4.
        $numeric = [['field' => 'per_row', 'value' => '4']];
        $this->assertTrue(VisibleWhen::passes($numeric, $get(['per_row' => 4])));

        $stacked = [
            ['field' => 'cta_mode', 'value' => 'add_to_cart'],
            ['field' => 'cta_item_type', 'operator' => 'not_equals', 'value' => 'package'],
        ];
        $this->assertTrue(VisibleWhen::passes($stacked, $get(['cta_mode' => 'add_to_cart', 'cta_item_type' => 'product'])));
        $this->assertFalse(VisibleWhen::passes($stacked, $get(['cta_mode' => 'add_to_cart', 'cta_item_type' => 'package'])));
        $this->assertFalse(VisibleWhen::passes($stacked, $get(['cta_mode' => 'link', 'cta_item_type' => 'product'])));
    }

    public function test_visible_when_with_unknown_operator_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(UpdateFlexibleSectionTypeAction::class)->execute(
            $this->makeType([['key' => 'heading', 'kind' => 'text']]),
            FlexibleSectionTypeData::validateAndCreate([
                'name' => 'Vocab Demo',
                'slug' => 'vocab-demo',
                'schema' => ['fields' => [
                    ['key' => 'heading', 'kind' => 'text', 'visible_when' => [
                        ['field' => 'mode', 'operator' => 'matches', 'value' => 'x'],
                    ]],
                ]],
            ]),
        );
    }

    // ─── Resolver pipeline ────────────────────────────────────────────

    public function test_resolver_ops_survive_admin_form_saves(): void
    {
        $type = $this->makeType(
            [['key' => 'product_ids', 'kind' => 'products', 'raw' => true]],
            [['op' => 'inline_products', 'input' => 'product_ids', 'output' => 'products']],
        );

        // The admin form round-trips schema.fields only — resolvers must be
        // carried forward, not silently stripped.
        $updated = app(UpdateFlexibleSectionTypeAction::class)->execute(
            $type,
            FlexibleSectionTypeData::validateAndCreate([
                'name' => 'Vocab Demo (renamed)',
                'slug' => 'vocab-demo',
                'schema' => ['fields' => [
                    ['key' => 'product_ids', 'kind' => 'products', 'raw' => true],
                    ['key' => 'heading', 'kind' => 'text'],
                ]],
            ]),
        );

        $this->assertSame(
            [['op' => 'inline_products', 'input' => 'product_ids', 'output' => 'products']],
            $updated->schema['resolvers'],
        );
    }

    public function test_unknown_resolver_op_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SectionResolverOps::validate([['op' => 'run_php', 'code' => 'evil()']]);
    }

    public function test_inline_op_without_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SectionResolverOps::validate([['op' => 'inline_product', 'output' => 'product']]);
    }

    public function test_cast_string_resolver_normalizes_nested_values(): void
    {
        $ops = app(SectionResolverOps::class);

        $result = $ops->apply(
            ['callouts' => [['position' => 0], ['position' => 1], ['position' => null]]],
            [['op' => 'cast_string', 'path' => 'callouts.*.position']],
        );

        $this->assertSame('0', $result['callouts'][0]['position']);
        $this->assertSame('1', $result['callouts'][1]['position']);
        $this->assertNull($result['callouts'][2]['position']);
    }

    public function test_resolve_cta_op_fans_out_over_repeater_path(): void
    {
        $product = Product::factory()->create();
        $ops = app(SectionResolverOps::class);

        $result = $ops->apply(
            ['callouts' => [
                ['cta_mode' => 'add_to_cart', 'cta_item_type' => 'product', 'cta_product_id' => $product->id],
                ['cta_mode' => 'link'],
            ]],
            [['op' => 'resolve_cta', 'path' => 'callouts.*']],
        );

        $this->assertSame($product->name, $result['callouts'][0]['cta_product']['name']);
        $this->assertNull($result['callouts'][1]['cta_product']);
    }

    // ─── Schema validation guards ─────────────────────────────────────

    public function test_second_cta_group_at_same_level_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(UpdateFlexibleSectionTypeAction::class)->execute(
            $this->makeType([['key' => 'heading', 'kind' => 'text']]),
            FlexibleSectionTypeData::validateAndCreate([
                'name' => 'Vocab Demo',
                'slug' => 'vocab-demo',
                'schema' => ['fields' => [
                    ['key' => 'primary', 'kind' => 'cta'],
                    ['key' => 'secondary', 'kind' => 'cta'],
                ]],
            ]),
        );
    }

    public function test_sibling_key_colliding_with_cta_group_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(UpdateFlexibleSectionTypeAction::class)->execute(
            $this->makeType([['key' => 'heading', 'kind' => 'text']]),
            FlexibleSectionTypeData::validateAndCreate([
                'name' => 'Vocab Demo',
                'slug' => 'vocab-demo',
                'schema' => ['fields' => [
                    ['key' => 'cta', 'kind' => 'cta'],
                    ['key' => 'cta_label', 'kind' => 'text'],
                ]],
            ]),
        );
    }
}
