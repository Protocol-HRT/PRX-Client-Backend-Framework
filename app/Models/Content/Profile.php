<?php

namespace App\Models\Content;

use App\Enums\ProfileType;
use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class Profile extends Model implements Sortable
{
    use GeneratesUniqueSlug, HasFactory, HasTags, SoftDeletes, SortableTrait;

    protected $fillable = [
        'uuid',
        'type',
        'name',
        'slug',
        'title',
        'credentials',
        'bio',
        'image_path',
        'is_featured',
        'is_published',
        'position',
        'links',
        'meta_title',
        'meta_description',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'position',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => ProfileType::class,
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'links' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Profile $profile): void {
            if (blank($profile->uuid)) {
                $profile->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
