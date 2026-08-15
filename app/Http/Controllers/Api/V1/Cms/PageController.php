<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Cms\PageResource;
use App\Models\Page;
use App\Services\Cms\CmsCache;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/pages           List all published pages (lightweight — no sections).
 * GET /api/v1/pages/{slug}    Retrieve a single published page with sections.
 */
class PageController extends ApiController
{
    public function __construct(private readonly CmsCache $cache) {}

    /**
     * List all published CMS pages.
     *
     * Returns a lightweight list of published pages (no section content) ordered by title.
     * Cached; invalidated instantly when CMS content is saved.
     *
     * @tags CMS
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        // Cache the resolved array, never Eloquent objects — serialized models
        // break across PHP runtimes sharing one Redis (FPM vs artisan serve).
        $payload = $this->cache->remember('pages.index', function (): array {
            $pages = Page::published()
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'meta_title', 'meta_description', 'og_image_path', 'noindex']);

            return PageResource::collection($pages)->resolve();
        });

        return $this->success($payload);
    }

    /**
     * Get a published CMS page by slug.
     *
     * Returns the page with all typed sections included.
     * Cached; invalidated instantly when CMS content is saved.
     *
     * @tags CMS
     *
     * @unauthenticated
     */
    public function show(string $slug): JsonResponse
    {
        $payload = $this->cache->remember("pages.{$slug}", function () use ($slug): ?array {
            $page = Page::published()
                ->where('slug', $slug)
                ->with('sections.globalSection')
                ->first();

            return $page === null
                ? null
                : (new PageResource($page))->withSections()->resolve();
        });

        if ($payload === null) {
            return $this->error('Page not found.', 404);
        }

        return $this->success($payload);
    }
}
