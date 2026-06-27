<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Blog\BlogPostResource;
use App\Models\Blog\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/blog/posts
 * GET /api/v1/blog/posts/{slug}
 */
class PostController extends ApiController
{
    /**
     * @return AnonymousResourceCollection
     *
     * Query params:
     *   ?category=slug      Filter by category slug
     *   ?tag=slug           Filter by tag slug
     *   ?featured=1         Featured posts only
     *   ?search=q           Title full-text search
     *   ?per_page=15        Page size (max 50)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 50);

        $posts = BlogPost::query()
            ->published()
            ->with(['categories', 'tags'])
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'categories',
                fn ($q) => $q->where('slug', $request->string('category'))
            ))
            ->when($request->filled('tag'), fn ($q) => $q->whereHas(
                'tags',
                fn ($q) => $q->where('slug', $request->string('tag'))
            ))
            ->when($request->boolean('featured'), fn ($q) => $q->where('featured', true))
            ->when($request->filled('search'), fn ($q) => $q->where(
                'title', 'like', '%'.$request->string('search').'%'
            ))
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return BlogPostResource::collection($posts);
    }

    public function show(BlogPost $post): JsonResponse
    {
        abort_if(! $post->isPublished(), 404);

        $post->load(['author', 'categories', 'tags']);

        return $this->success((new BlogPostResource($post))->toArray(request()));
    }
}
