<?php

namespace Tests\Feature\Cms;

use App\Actions\Cms\RestorePageRevisionAction;
use App\Actions\Pages\UpdatePageAction;
use App\Data\Pages\PageData;
use App\Enums\Cms\RevisionCause;
use App\Models\Cms\GlobalSection;
use App\Models\Cms\PageRevision;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\PageRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PageRevisionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function freshMemo(): void
    {
        app(PageRevisionService::class)->flushMemo();
    }

    private function pageData(Page $page, array $overrides = []): PageData
    {
        $page = $page->fresh();

        return PageData::validateAndCreate(array_merge([
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status->value,
            'template' => $page->template ?? 'default',
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'og_image_path' => $page->og_image_path,
            'noindex' => (bool) $page->noindex,
            'publish_at' => $page->publish_at?->toISOString(),
            'title_banner' => $page->title_banner,
        ], $overrides));
    }

    public function test_updating_a_page_snapshots_the_pre_edit_state(): void
    {
        $page = Page::factory()->create(['title' => 'Original title']);
        PageRevision::query()->delete();
        $this->freshMemo();

        app(UpdatePageAction::class)->execute($page, $this->pageData($page, ['title' => 'New title']));

        $revision = PageRevision::query()->where('page_id', $page->id)->latest('id')->first();

        $this->assertNotNull($revision);
        $this->assertSame(RevisionCause::PageSaved, $revision->cause);
        $this->assertSame('Original title', $revision->snapshot['page']['title']);
        $this->assertSame('New title', $page->fresh()->title);
    }

    public function test_bulk_section_changes_produce_one_snapshot_per_request(): void
    {
        $page = Page::factory()->create();
        PageRevision::query()->delete();
        $this->freshMemo();

        PageSection::factory()->count(5)->create(['page_id' => $page->id]);

        $this->assertSame(1, PageRevision::query()->where('page_id', $page->id)->count());
    }

    public function test_identical_states_are_deduplicated_by_content_hash(): void
    {
        $page = Page::factory()->create();
        PageRevision::query()->delete();

        $this->freshMemo();
        app(UpdatePageAction::class)->execute($page, $this->pageData($page->fresh()));

        $this->freshMemo();
        app(UpdatePageAction::class)->execute($page->fresh(), $this->pageData($page->fresh()));

        $this->assertSame(1, PageRevision::query()->where('page_id', $page->id)->count());
    }

    public function test_restore_returns_page_and_sections_to_snapshot_state(): void
    {
        $page = Page::factory()->create(['title' => 'Version A']);
        PageSection::factory()->hero()->create(['page_id' => $page->id]);

        PageRevision::query()->delete();
        $this->freshMemo();

        // Edit burst: retitle + replace the hero with a faq.
        app(UpdatePageAction::class)->execute($page, $this->pageData($page, ['title' => 'Version B']));
        $page->sections()->delete();
        PageSection::factory()->create(['page_id' => $page->id, 'type' => 'faq']);

        $revision = PageRevision::query()->where('page_id', $page->id)->oldest('id')->first();
        $this->assertSame('Version A', $revision->snapshot['page']['title']);

        $this->freshMemo();
        app(RestorePageRevisionAction::class)->execute($revision);

        $page->refresh();
        $this->assertSame('Version A', $page->title);
        $this->assertSame(['hero'], $page->sections()->pluck('type')->all());

        // The restore itself captured the pre-restore state for undo.
        $this->assertTrue(
            PageRevision::query()->where('page_id', $page->id)->get()
                ->contains(fn (PageRevision $r): bool => $r->cause === RevisionCause::Restored),
        );
    }

    public function test_restore_materializes_deleted_global_as_local_copy(): void
    {
        $page = Page::factory()->create();
        $global = GlobalSection::factory()->create(['data' => ['heading' => 'Global content']]);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => $global->type,
            'data' => null,
            'global_section_id' => $global->id,
        ]);

        PageRevision::query()->delete();
        $this->freshMemo();
        $revision = app(PageRevisionService::class)->snapshot($page, RevisionCause::SectionsSaved);

        $page->sections()->delete();
        $global->delete();

        $this->freshMemo();
        app(RestorePageRevisionAction::class)->execute($revision);

        $section = $page->sections()->first();
        $this->assertNull($section->global_section_id);
        $this->assertSame('Global content', $section->data['heading']);
    }

    public function test_revisions_are_pruned_to_the_configured_cap(): void
    {
        config(['cms.revisions.keep' => 3]);

        $page = Page::factory()->create();
        PageRevision::query()->delete();

        foreach (range(1, 6) as $i) {
            $this->freshMemo();
            app(UpdatePageAction::class)->execute($page->fresh(), $this->pageData($page->fresh(), ['title' => "Title {$i}"]));
        }

        $this->assertSame(3, PageRevision::query()->where('page_id', $page->id)->count());
    }
}
