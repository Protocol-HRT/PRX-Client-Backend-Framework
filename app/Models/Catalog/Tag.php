<?php

namespace App\Models\Catalog;

use App\Models\Concerns\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Tag extends Model implements Sortable
{
    use GeneratesUniqueSlug, HasFactory, SoftDeletes, SortableTrait;

    protected $fillable = [
        'name',
        'slug',
        'color',
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

    protected static function newFactory(): \Database\Factories\Catalog\TagFactory
    {
        return \Database\Factories\Catalog\TagFactory::new();
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }

    public function packages(): MorphToMany
    {
        return $this->morphedByMany(Package::class, 'taggable');
    }

    public function plans(): MorphToMany
    {
        return $this->morphedByMany(Plan::class, 'taggable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
