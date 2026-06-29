<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Blog\BlogTagResource;
use App\Models\Catalog\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/blog/tags
 *
 * Returns all visible tags from the unified tag pool.
 * Tags are shared across all content types (blog, catalog, FAQ, profiles).
 */
class BlogTagController extends ApiController
{
    /**
     * List all visible blog tags.
     *
     * Returns tags from the unified tag pool that are marked visible, ordered by name.
     * Tags are shared across blog, catalog, FAQ, and profiles.
     *
     * @tags Blog
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        $tags = Tag::query()
            ->where('is_visible', true)
            ->orderBy('name')
            ->get();

        return BlogTagResource::collection($tags);
    }
}
