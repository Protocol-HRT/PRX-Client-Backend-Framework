<?php

namespace App\Models\Content;

use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class FaqItem extends Model implements Sortable
{
    use HasFactory, HasTags, SoftDeletes, SortableTrait;

    protected $fillable = [
        'faq_category_id',
        'question',
        'answer',
        'is_published',
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
            'is_published' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Catalog products this FAQ is attached to (faqables pivot). */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'faqable')->withPivot('position');
    }

    /** Catalog packages this FAQ is attached to (faqables pivot). */
    public function packages(): MorphToMany
    {
        return $this->morphedByMany(Package::class, 'faqable')->withPivot('position');
    }
}
