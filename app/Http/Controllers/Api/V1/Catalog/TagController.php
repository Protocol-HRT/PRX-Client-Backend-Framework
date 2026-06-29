<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Catalog\TagResource;
use App\Models\Catalog\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/catalog/tags
 */
class TagController extends ApiController
{
    /**
     * List all visible catalog tags.
     *
     * Returns visible tags ordered by position then name. Tags are shared across catalog,
     * blog, FAQ, and profiles.
     *
     * @tags Catalog
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        $tags = Tag::query()
            ->where('is_visible', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return TagResource::collection($tags);
    }
}
