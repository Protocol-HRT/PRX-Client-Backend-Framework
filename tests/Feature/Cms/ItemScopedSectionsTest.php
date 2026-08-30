<?php

namespace Tests\Feature\Cms;

use App\Enums\CatalogStatus;
use App\Models\Catalog\Product;
use App\Services\Cms\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `item-faqs` and `item-reviews` — the two sections that read the record they
 * are attached to rather than carrying content of their own.
 *
 * They exist because FAQs and reviews used to render on a detail page purely
 * because the record had some: no toggle, no position, no way to leave them
 * off. The operator's summary of the problem was "Data does not = display
 * always!", and these are the half of the answer that makes display a choice.
 */
class ItemScopedSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function registry(): SectionRegistry
    {
        return app(SectionRegistry::class);
    }

    public function test_the_item_scoped_types_are_offered_on_a_catalog_record(): void
    {
        $options = $this->registry()->options('catalog');

        $this->assertArrayHasKey('item-faqs', $options);
        $this->assertArrayHasKey('item-reviews', $options);
    }

    /**
     * There is no record to read on a CMS page, so offering them there would
     * let an operator author a section that can only ever render nothing.
     */
    public function test_the_item_scoped_types_are_not_offered_on_a_page(): void
    {
        $options = $this->registry()->options('page');

        $this->assertArrayNotHasKey('item-faqs', $options);
        $this->assertArrayNotHasKey('item-reviews', $options);
    }

    /**
     * Context gates the PICKER, never resolution. A section already authored
     * has to keep rendering whatever the contexts say, because silently
     * dropping content an operator wrote is worse than an odd dropdown entry.
     */
    public function test_context_filtering_does_not_affect_resolution(): void
    {
        $all = $this->registry()->all();

        $this->assertArrayHasKey('item-faqs', $all);
        $this->assertArrayHasKey('item-reviews', $all);
    }

    /**
     * Their content is the relation, not the copy above it, so they must
     * survive `has_content` with every field blank — otherwise an operator who
     * adds one and writes no heading watches it silently disappear.
     */
    public function test_they_render_with_no_authored_copy(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        foreach (['item-faqs', 'item-reviews'] as $index => $type) {
            $product->sections()->create([
                'type' => $type,
                'data' => ['heading' => null],
                'enabled' => true,
                'position' => $index,
            ]);
        }

        $types = $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->json('data.sections.*.type');

        $this->assertSame(['item-faqs', 'item-reviews'], $types);
    }

    /** The whole point: switching one off removes it from the payload. */
    public function test_disabling_one_removes_it_from_the_payload(): void
    {
        $product = Product::factory()->create(['status' => CatalogStatus::Published]);

        $product->sections()->create([
            'type' => 'item-faqs',
            'data' => ['heading' => null],
            'enabled' => false,
            'position' => 0,
        ]);

        $this->getJson("/api/v1/catalog/products/{$product->slug}")
            ->assertOk()
            ->assertJsonCount(0, 'data.sections');
    }
}
