<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Enums\PageStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Cms\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v1/pages           List all published pages (lightweight — no sections).
 * GET /api/v1/pages/{slug}    Retrieve a single published page with sections.
 */
class PageController extends ApiController
{
    public function index(): JsonResponse
    {
        $pages = Cache::remember('api.v1.pages.index', 300, function () {
            return Page::published()
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'meta_title', 'meta_description', 'og_image_path', 'noindex']);
        });

        return $this->success(PageResource::collection($pages)->resolve());
    }

    public function show(string $slug): JsonResponse
    {
        $page = Cache::remember("api.v1.pages.{$slug}", 300, function () use ($slug) {
            return Page::published()
                ->where('slug', $slug)
                ->with('sections')
                ->first();
        });

        if ($page === null) {
            return $this->error('Page not found.', 404);
        }

        return $this->success((new PageResource($page))->withSections()->resolve());
    }
}
