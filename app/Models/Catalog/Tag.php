<?php

namespace App\Models\Catalog;

use App\Models\Blog\BlogPost;
use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\Content\FaqItem;
use App\Models\Content\Profile;
use Database\Factories\Catalog\TagFactory;
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

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
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

    public function blogPosts(): MorphToMany
    {
        return $this->morphedByMany(BlogPost::class, 'taggable');
    }

    public function faqItems(): MorphToMany
    {
        return $this->morphedByMany(FaqItem::class, 'taggable');
    }

    public function profiles(): MorphToMany
    {
        return $this->morphedByMany(Profile::class, 'taggable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
