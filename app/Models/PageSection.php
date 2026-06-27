<?php

namespace App\Models;

use App\Enums\SectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class PageSection extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    protected $fillable = [
        'page_id',
        'type',
        'position',
        'enabled',
        'data',
        'anchor_id',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => SectionType::class,
            'data' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Restrict sortable rows to siblings on the same page.
     */
    public function buildSortQuery()
    {
        return static::query()->where('page_id', $this->page_id);
    }
}
