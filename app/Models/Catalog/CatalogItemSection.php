<?php

namespace App\Models\Catalog;

use App\Models\Cms\GlobalSection;
use Database\Factories\Catalog\CatalogItemSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * An injectable content section on a catalog record's detail page —
 * PageSection's morph-attached sibling. Exposes the same attribute surface
 * (type, data, enabled, anchor_id, globalSection) so the CMS
 * SectionDataTransformer serializes both into the identical frontend
 * envelope, and every registered section type (video-embed, image-text
 * split, testimonials, …) is available per product/package without new
 * plumbing. Global blocks compose the same way pages use them: author
 * once, attach to any number of records.
 */
class CatalogItemSection extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    protected $fillable = [
        'sectionable_type',
        'sectionable_id',
        'type',
        'data',
        'global_section_id',
        'anchor_id',
        'enabled',
        'position',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            // Plain string (not an enum): admin-defined flexible section
            // types share this column — same rule as PageSection.type.
            'type' => 'string',
            'data' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function sectionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function globalSection(): BelongsTo
    {
        return $this->belongsTo(GlobalSection::class);
    }

    /** Restrict sortable rows to siblings on the same catalog record. */
    public function buildSortQuery()
    {
        return static::query()
            ->where('sectionable_type', $this->sectionable_type)
            ->where('sectionable_id', $this->sectionable_id);
    }

    protected static function newFactory(): CatalogItemSectionFactory
    {
        return CatalogItemSectionFactory::new();
    }
}
