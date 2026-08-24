<?php

namespace App\Services\Cms;

use App\Http\Resources\Api\V1\Content\FaqCategoryResource;
use App\Models\Content\FaqCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Inlines the central FAQ dataset into section payloads so the frontend
 * renders grouped FAQ panels from the page fetch alone — no second round
 * trip to /api/v1/faq, and no duplicate authoring of questions that already
 * live in Content → FAQ.
 *
 * Only visible categories and published items are ever emitted; manual
 * selections preserve the admin's chosen order and silently drop categories
 * that were since hidden. Categories left with no published items are
 * dropped entirely so an empty panel can never render.
 *
 * Payloads are fully serialized to plain arrays (not ->resolve(), which
 * leaves nested resource collections as objects): section data is cached by
 * CmsCache, and resource objects do not survive the serialize round-trip.
 */
class FaqInliner
{
    /**
     * Visible categories with published items, in admin order.
     *
     * @return list<array<string, mixed>>
     */
    public function allCategories(int $limit = 24): array
    {
        $categories = $this->baseQuery()
            ->orderBy('position')
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $this->present($categories);
    }

    /**
     * Categories chosen by hand, in the order the admin selected them.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function categoriesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $categories = $this->baseQuery()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (FaqCategory $category): int => (int) array_search($category->id, $ids))
            ->values();

        return $this->present($categories);
    }

    private function baseQuery(): Builder
    {
        return FaqCategory::query()
            ->where('is_visible', true)
            ->with('publishedItems');
    }

    /**
     * @param  Collection<int, FaqCategory>  $categories
     * @return list<array<string, mixed>>
     */
    private function present(Collection $categories): array
    {
        // A category with nothing published would render as an empty panel —
        // drop it here rather than making every frontend guard for it.
        $withItems = $categories->filter(
            fn (FaqCategory $category): bool => $category->publishedItems->isNotEmpty()
        )->values();

        return $this->toPlainArray(FaqCategoryResource::collection($withItems));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toPlainArray(mixed $collection): array
    {
        return json_decode(json_encode($collection), true) ?? [];
    }
}
