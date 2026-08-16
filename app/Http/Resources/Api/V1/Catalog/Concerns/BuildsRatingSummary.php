<?php

namespace App\Http\Resources\Api\V1\Catalog\Concerns;

trait BuildsRatingSummary
{
    /**
     * Rating aggregate from the eager-loaded approvedReviews relation.
     * Null when nothing is approved — the storefront renders no stars
     * rather than a fabricated zero-count rating.
     *
     * @return array{average: float, count: int}|null
     */
    private function ratingFromLoadedReviews(): ?array
    {
        $count = $this->approvedReviews->count();

        if ($count === 0) {
            return null;
        }

        return [
            'average' => round((float) $this->approvedReviews->avg('rating'), 1),
            'count' => $count,
        ];
    }
}
