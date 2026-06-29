<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Content\FaqCategoryResource;
use App\Http\Resources\Api\V1\Content\FaqItemResource;
use App\Models\Content\FaqCategory;
use App\Models\Content\FaqItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/faq                    All published FAQ items (optionally filtered by category)
 * GET /api/v1/faq/categories         All visible FAQ categories with their published items
 * GET /api/v1/faq/categories/{slug}  Single category with published items
 */
class FaqController extends ApiController
{
    /**
     * List all published FAQ items.
     *
     * Returns published FAQ items ordered by position. Optionally filter by category slug
     * or tag slug. Includes the item's category and tags.
     *
     * @tags Content
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FaqItem::published()->with(['category', 'tags'])->orderBy('position');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category')));
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $request->string('tag')));
        }

        return FaqItemResource::collection($query->get());
    }

    /**
     * List FAQ categories with their published items.
     *
     * Returns all visible FAQ categories ordered by position, each with their published
     * items (including tags) embedded.
     *
     * @tags Content
     *
     * @unauthenticated
     */
    public function categories(): AnonymousResourceCollection
    {
        $categories = FaqCategory::query()
            ->where('is_visible', true)
            ->with('publishedItems.tags')
            ->orderBy('position')
            ->get();

        return FaqCategoryResource::collection($categories);
    }

    /**
     * Get a single FAQ category with its published items.
     *
     * Returns the visible FAQ category matching the slug, including its published items
     * with tags. Returns 404 if the category is not found or not visible.
     *
     * @tags Content
     *
     * @unauthenticated
     */
    public function category(string $slug): JsonResponse
    {
        $category = FaqCategory::query()
            ->where('is_visible', true)
            ->where('slug', $slug)
            ->with('publishedItems.tags')
            ->firstOrFail();

        return $this->success((new FaqCategoryResource($category))->toArray(request()));
    }
}
