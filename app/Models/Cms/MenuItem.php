<?php

namespace App\Models\Cms;

use App\Enums\Cms\MenuLinkType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class MenuItem extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'label',
        'link_type',
        'linkable_type',
        'linkable_id',
        'url',
        'target',
        'icon',
        'badge',
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
            'link_type' => MenuLinkType::class,
            'enabled' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Sortable siblings: same menu AND same parent.
     */
    public function buildSortQuery()
    {
        return static::query()
            ->where('menu_id', $this->menu_id)
            ->where('parent_id', $this->parent_id);
    }

    /**
     * Distance from a root item (root = 1). Walks ancestors — menu trees are
     * small and this only runs at authoring time.
     */
    public function depth(): int
    {
        $depth = 1;
        $current = $this->parent;

        while ($current !== null) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }
}
