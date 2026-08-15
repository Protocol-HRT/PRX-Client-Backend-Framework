<?php

namespace App\Models\Cms;

use App\Enums\Cms\Region;
use App\Enums\Cms\RegionItemKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * One entry in a fixed layout region: either a section owned by the region
 * (kind=section, own type+data), a reference to a global block, or a menu.
 */
class RegionItem extends Model
{
    use HasFactory, SortableTrait;

    protected $fillable = [
        'region',
        'kind',
        'section_type',
        'data',
        'global_section_id',
        'menu_id',
        'position',
        'enabled',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'region' => Region::class,
            'kind' => RegionItemKind::class,
            'data' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function globalSection(): BelongsTo
    {
        return $this->belongsTo(GlobalSection::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Sortable siblings are items within the same region.
     */
    public function buildSortQuery()
    {
        return static::query()->where('region', $this->region);
    }
}
