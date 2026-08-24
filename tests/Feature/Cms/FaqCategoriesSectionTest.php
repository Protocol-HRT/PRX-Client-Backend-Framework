<?php

namespace Tests\Feature\Cms;

use App\Models\Content\FaqCategory;
use App\Models\Content\FaqItem;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The `faq-categories` section inlines the central Content → FAQ dataset at
 * API read time, so a page fetch alone carries every grouped question.
 */
class FaqCategoriesSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function pageWithSection(array $data, string $slug = 'faq-test'): void
    {
        $page = Page::factory()->create(['slug' => $slug]);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'faq-categories',
            'data' => $data,
        ]);
    }

    /**
     * SortableTrait's `sort_when_creating` assigns the next position on
     * insert and ignores one passed to create(), so set it afterwards to
     * author an explicit order.
     */
    private function category(string $name, int $position, bool $visible = true): FaqCategory
    {
        $category = FaqCategory::factory()->create([
            'name' => $name,
            'is_visible' => $visible,
        ]);

        $category->update(['position' => $position]);

        return $category;
    }

    private function item(FaqCategory $category, string $question, bool $published = true, int $position = 1): FaqItem
    {
        $item = FaqItem::factory()->create([
            'faq_category_id' => $category->id,
            'question' => $question,
            'is_published' => $published,
        ]);

        $item->update(['position' => $position]);

        return $item;
    }

    private function sectionData(string $slug = 'faq-test'): array
    {
        return $this->getJson("/api/v1/pages/{$slug}")->json('data.sections.0.data');
    }

    public function test_all_mode_inlines_visible_categories_in_position_order(): void
    {
        $second = $this->category('Billing', 2);
        $first = $this->category('General', 1);
        $this->item($first, 'What is this?');
        $this->item($second, 'How much?');

        $this->pageWithSection(['mode' => 'all', 'limit' => 24]);

        $this->assertSame(
            ['General', 'Billing'],
            array_column($this->sectionData()['categories'], 'name'),
        );
    }

    public function test_manual_mode_preserves_admin_selected_order(): void
    {
        $a = $this->category('Alpha', 1);
        $b = $this->category('Beta', 2);
        $c = $this->category('Gamma', 3);
        foreach ([$a, $b, $c] as $category) {
            $this->item($category, "Q for {$category->name}");
        }

        $this->pageWithSection([
            'mode' => 'manual',
            'category_ids' => [$c->id, $a->id, $b->id],
        ]);

        $this->assertSame(
            ['Gamma', 'Alpha', 'Beta'],
            array_column($this->sectionData()['categories'], 'name'),
        );
    }

    public function test_hidden_categories_are_dropped(): void
    {
        $visible = $this->category('Visible', 1);
        $hidden = $this->category('Hidden', 2, visible: false);
        $this->item($visible, 'Shown');
        $this->item($hidden, 'Not shown');

        $this->pageWithSection(['mode' => 'all']);

        $this->assertSame(['Visible'], array_column($this->sectionData()['categories'], 'name'));
    }

    public function test_unpublished_items_are_dropped(): void
    {
        $category = $this->category('General', 1);
        $this->item($category, 'Published one', position: 1);
        $this->item($category, 'Draft one', published: false, position: 2);

        $this->pageWithSection(['mode' => 'all']);

        $items = $this->sectionData()['categories'][0]['items'];

        $this->assertSame(['Published one'], array_column($items, 'question'));
    }

    public function test_category_with_no_published_items_is_dropped_entirely(): void
    {
        $withItems = $this->category('Has questions', 1);
        $empty = $this->category('All drafts', 2);
        $this->item($withItems, 'Live');
        $this->item($empty, 'Draft', published: false);

        $this->pageWithSection(['mode' => 'all']);

        $this->assertSame(
            ['Has questions'],
            array_column($this->sectionData()['categories'], 'name'),
        );
    }

    public function test_items_carry_question_and_answer(): void
    {
        $category = $this->category('General', 1);
        FaqItem::factory()->create([
            'faq_category_id' => $category->id,
            'question' => 'What are GLP-1 medications?',
            'answer' => 'Prescription drugs that improve blood sugar control.',
            'is_published' => true,
        ]);

        $this->pageWithSection(['mode' => 'all']);

        $item = $this->sectionData()['categories'][0]['items'][0];

        $this->assertSame('What are GLP-1 medications?', $item['question']);
        $this->assertSame('Prescription drugs that improve blood sugar control.', $item['answer']);
    }

    public function test_editing_an_faq_item_busts_the_cached_page_payload(): void
    {
        $category = $this->category('General', 1);
        $item = $this->item($category, 'Original question');

        $this->pageWithSection(['mode' => 'all']);

        $this->assertSame(
            'Original question',
            $this->sectionData()['categories'][0]['items'][0]['question'],
        );

        // Without FaqItem being observed by CmsCacheObserver this still
        // returns the cached "Original question" until the TTL expires.
        $item->update(['question' => 'Edited question']);

        $this->assertSame(
            'Edited question',
            $this->sectionData()['categories'][0]['items'][0]['question'],
        );
    }
}
