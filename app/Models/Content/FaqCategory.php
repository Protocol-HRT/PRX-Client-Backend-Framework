<?php

namespace App\Models\Content;

use App\Models\Concerns\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class FaqCategory extends Model implements Sortable
{
    use GeneratesUniqueSlug, HasFactory, SoftDeletes, SortableTrait;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_visible',
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
            'is_visible' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class);
    }

    public function publishedItems(): HasMany
    {
        return $this->hasMany(FaqItem::class)->where('is_published', true)->orderBy('position');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
