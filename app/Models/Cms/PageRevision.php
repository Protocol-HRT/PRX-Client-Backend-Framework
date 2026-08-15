<?php

namespace App\Models\Cms;

use App\Enums\Cms\RevisionCause;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A point-in-time snapshot of a page and its sections, written just before
 * the first change in an editing burst. Restoring returns the page to this
 * captured state.
 */
class PageRevision extends Model
{
    protected $fillable = [
        'page_id',
        'snapshot',
        'content_hash',
        'cause',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'cause' => RevisionCause::class,
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sectionCount(): int
    {
        return count($this->snapshot['sections'] ?? []);
    }
}
