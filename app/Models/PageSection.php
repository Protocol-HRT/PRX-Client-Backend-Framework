<?php

namespace App\Models;

use App\Contracts\Cms\SectionDefinition;
use App\Models\Cms\GlobalSection;
use App\Services\Cms\SectionRegistry;
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
        'global_section_id',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            // Plain string, not the SectionType enum: admin-defined flexible
            // section types share this column and can't be enum cases.
            'type' => 'string',
            'data' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function globalSection(): BelongsTo
    {
        return $this->belongsTo(GlobalSection::class);
    }

    /**
     * Resolve this section's type definition (code blueprint or flexible type).
     * Null when the type no longer exists (e.g. deleted flexible type).
     */
    public function definition(): ?SectionDefinition
    {
        return app(SectionRegistry::class)->resolve($this->type);
    }

    /**
     * Restrict sortable rows to siblings on the same page.
     */
    public function buildSortQuery()
    {
        return static::query()->where('page_id', $this->page_id);
    }
}
