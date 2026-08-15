<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Enums\Cms\RevisionCause;
use App\Models\Cms\GlobalSection;
use App\Models\Cms\PageRevision;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\PageRevisionService;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class RestorePageRevisionAction
{
    use Transacts;

    public function __construct(private readonly PageRevisionService $revisions) {}

    public function execute(PageRevision $revision): Page
    {
        return $this->tx(function () use ($revision) {
            $page = $revision->page;

            if ($page === null) {
                throw new RuntimeException('The page for this revision no longer exists.');
            }

            // Capture the current state first so the restore itself is undoable.
            $this->revisions->flushMemo();
            $this->revisions->snapshot($page, RevisionCause::Restored);

            $snapshot = $revision->snapshot;

            $page->update([
                ...$snapshot['page'],
                'updated_by' => Auth::id(),
            ]);

            // Recreate sections without firing the snapshot observer again.
            PageSection::withoutEvents(function () use ($page, $snapshot): void {
                $page->sections()->delete();

                foreach ($snapshot['sections'] ?? [] as $row) {
                    $globalId = $row['global_section_id'] ?? null;

                    if ($globalId !== null && ! GlobalSection::query()->whereKey($globalId)->exists()) {
                        // Referenced global was deleted since the snapshot —
                        // materialize the captured copy as an independent section.
                        $row['data'] = $row['global_data'] ?? $row['data'];
                        $globalId = null;
                    }

                    $page->sections()->create([
                        'type' => $row['type'],
                        'position' => $row['position'],
                        'enabled' => $row['enabled'] ?? true,
                        'anchor_id' => $row['anchor_id'] ?? null,
                        'data' => $row['data'],
                        'global_section_id' => $globalId,
                    ]);
                }
            });

            return $page->fresh();
        });
    }
}
