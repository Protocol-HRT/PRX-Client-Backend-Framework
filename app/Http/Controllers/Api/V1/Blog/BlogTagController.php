<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Blog\BlogTagResource;
use App\Models\Blog\BlogTag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/blog/tags
 */
class BlogTagController extends ApiController
{
    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $tags = BlogTag::query()
            ->where('is_visible', true)
            ->orderBy('name')
            ->get();

        return BlogTagResource::collection($tags);
    }
}
