<?php

namespace App\Services\Cms;

use App\Enums\Cms\RevisionCause;
use App\Models\Cms\PageRevision;
use App\Models\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Writes page revision snapshots.
 *
 * Semantics: a revision captures the page's state BEFORE the first change of
 * an editing burst — restoring returns to how things were before that burst.
 * A per-request memo collapses bulk operations (10-row drag reorder, bulk
 * delete) into a single snapshot, and a content hash skips writes when the
 * captured state equals the latest stored revision.
 *
 * Registered as a singleton (the memo is per-request state).
 */
class PageRevisionService
{
    /** @var array<int, true> */
    private array $snapshotted = [];

    /**
     * Snapshot once per page per request — used by the section observer,
     * where one admin gesture can fire many model events.
     */
    public function queueForPage(int $pageId, RevisionCause $cause): void
    {
        if (isset($this->snapshotted[$pageId])) {
            return;
        }

        $page = Page::query()->find($pageId);

        if ($page === null) {
            return;
        }

        $this->snapshot($page, $cause);
    }

    public function snapshot(Page $page, RevisionCause $cause): ?PageRevision
    {
        $this->snapshotted[$page->id] = true;

        $snapshot = $this->capture($page);
        $hash = hash('sha256', json_encode($snapshot));

        $latest = PageRevision::query()
            ->where('page_id', $page->id)
            ->latest('id')
            ->first();

        if ($latest?->content_hash === $hash) {
            return null;
        }

        $revision = PageRevision::query()->create([
            'page_id' => $page->id,
            'snapshot' => $snapshot,
            'content_hash' => $hash,
            'cause' => $cause->value,
            'created_by' => Auth::id(),
        ]);

        $this->prune($page->id);

        return $revision;
    }

    /**
     * Testing/long-running-process hook: clear the once-per-request memo.
     */
    public function flushMemo(): void
    {
        $this->snapshotted = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function capture(Page $page): array
    {
        // Snapshot persisted state, not possibly-stale in-memory attributes.
        $page = $page->fresh() ?? $page;

        $sections = $page->sections()
            ->with('globalSection')
            ->orderBy('position')
            ->get()
            ->map(fn ($section): array => [
                'type' => $section->type,
                'position' => $section->position,
                'enabled' => (bool) $section->enabled,
                'anchor_id' => $section->anchor_id,
                'data' => $section->data,
                'global_section_id' => $section->global_section_id,
                // Captured copy: lets a restore materialize the content even if
                // the referenced global is deleted between snapshot and restore.
                'global_data' => $section->globalSection?->data,
            ])
            ->all();

        return [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'template' => $page->template,
                'title_banner' => $page->title_banner,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'og_image_path' => $page->og_image_path,
                'noindex' => (bool) $page->noindex,
                'publish_at' => $page->publish_at?->toISOString(),
            ],
            'sections' => $sections,
        ];
    }

    private function prune(int $pageId): void
    {
        $keep = (int) config('cms.revisions.keep', 30);

        $cutoffId = PageRevision::query()
            ->where('page_id', $pageId)
            ->orderByDesc('id')
            ->skip($keep - 1)
            ->value('id');

        if ($cutoffId !== null) {
            PageRevision::query()
                ->where('page_id', $pageId)
                ->where('id', '<', $cutoffId)
                ->delete();
        }
    }
}
