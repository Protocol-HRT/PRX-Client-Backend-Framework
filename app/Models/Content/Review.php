<?php

namespace App\Models\Content;

use Database\Factories\Content\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base review record — deliberately thin. Rows are admin-curated today
 * (`source` = "admin"); patient-portal submissions and per-client external
 * review integrations are expected to write into this same table later with
 * their own `source` values, so nothing here assumes how a review was
 * collected. Only approved reviews ever reach the storefront API.
 */
class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rating',
        'author_name',
        'title',
        'body',
        'is_approved',
        'source',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }
}
