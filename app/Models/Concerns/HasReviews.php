<?php

namespace App\Models\Concerns;

use App\Models\Content\Review;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasReviews
{
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /** Storefront-facing subset, newest first. */
    public function approvedReviews(): MorphMany
    {
        return $this->reviews()
            ->where('is_approved', true)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id');
    }

    /**
     * Aggregate for the storefront ratings row. Null when there are no
     * approved reviews — the frontend renders nothing rather than a
     * fabricated zero-count rating.
     *
     * @return array{average: float, count: int}|null
     */
    public function ratingSummary(): ?array
    {
        $stats = $this->approvedReviews()
            ->toBase()
            ->selectRaw('avg(rating) as average, count(*) as total')
            ->first();

        if (! $stats || (int) $stats->total === 0) {
            return null;
        }

        return [
            'average' => round((float) $stats->average, 1),
            'count' => (int) $stats->total,
        ];
    }
}
