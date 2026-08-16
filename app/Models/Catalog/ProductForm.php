<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\ProductFormFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class ProductForm extends Model implements Sortable
{
    use HasFactory, HasSlug, SoftDeletes, SortableTrait;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_volume',
        'is_active',
        'position',
        'provider_value',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite();
    }

    protected function casts(): array
    {
        return [
            'requires_volume' => 'boolean',
            'is_active' => 'boolean',
            'provider_value' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): ProductFormFactory
    {
        return ProductFormFactory::new();
    }
}
