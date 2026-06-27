<?php

namespace App\Models\Blog;

use App\Models\Concerns\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class BlogTag extends Model implements Sortable
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

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            BlogPost::class,
            'blog_tag_post',
            'blog_tag_id',
            'blog_post_id'
        );
    }

    protected static function newFactory(): \Database\Factories\Blog\BlogTagFactory
    {
        return \Database\Factories\Blog\BlogTagFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
