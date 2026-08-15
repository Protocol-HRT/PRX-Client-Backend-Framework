<?php

namespace App\Observers;

use App\Enums\Cms\RevisionCause;
use App\Models\PageSection;
use App\Services\Cms\PageRevisionService;

/**
 * Snapshots a page revision just before its sections change. `saving` and
 * `deleting` fire pre-write, so the captured state is the pre-edit state;
 * the service's per-request memo collapses bulk gestures into one snapshot.
 */
class PageSectionObserver
{
    public function __construct(private readonly PageRevisionService $revisions) {}

    public function saving(PageSection $section): void
    {
        // New sections have no pre-existing state of their own, but the page
        // they're added to does — snapshot it before the addition lands.
        $this->queue($section);
    }

    public function deleting(PageSection $section): void
    {
        $this->queue($section);
    }

    private function queue(PageSection $section): void
    {
        if ($section->page_id !== null) {
            $this->revisions->queueForPage((int) $section->page_id, RevisionCause::SectionsSaved);
        }
    }
}
