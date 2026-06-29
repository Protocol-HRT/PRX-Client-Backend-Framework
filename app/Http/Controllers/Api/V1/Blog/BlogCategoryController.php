<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Blog\BlogCategoryResource;
use App\Models\Blog\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/blog/categories
 * GET /api/v1/blog/categories/{slug}
 */
class BlogCategoryController extends ApiController
{
    /**
     * List visible blog categories.
     *
     * Returns all visible categories ordered by position then name, with a published post count.
     *
     *
     * @tags Blog
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = BlogCategory::query()
            ->where('is_visible', true)
            ->withCount('posts')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return BlogCategoryResource::collection($categories);
    }

    /**
     * Get a visible blog category by slug.
     *
     * @tags Blog
     *
     * @unauthenticated
     */
    public function show(BlogCategory $blogCategory): JsonResponse
    {
        abort_if(! $blogCategory->is_visible, 404);

        return $this->success((new BlogCategoryResource($blogCategory))->toArray(request()));
    }
}
