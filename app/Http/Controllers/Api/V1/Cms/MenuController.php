<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Cms\Menu;
use App\Services\Cms\CmsCache;
use App\Services\Cms\MenuTreeBuilder;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/menus           List all menus (name + slug only).
 * GET /api/v1/menus/{slug}    Retrieve a menu's nested item tree.
 */
class MenuController extends ApiController
{
    public function __construct(
        private readonly CmsCache $cache,
        private readonly MenuTreeBuilder $trees,
    ) {}

    /**
     * List all menus.
     *
     * Lightweight index of menu names and slugs. Fetch a menu's items via
     * /api/v1/menus/{slug}. Cached; invalidated instantly on save.
     *
     * @tags CMS
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $menus = $this->cache->remember('menus.index', function () {
            return Menu::query()
                ->orderBy('name')
                ->get(['name', 'slug', 'description'])
                ->map(fn (Menu $menu): array => [
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'description' => $menu->description,
                ])
                ->all();
        });

        return $this->success($menus);
    }

    /**
     * Get a menu by slug with its nested item tree.
     *
     * Items link to internal entities by `{type, slug}` (the frontend owns
     * route patterns) or to direct URLs/anchors by `{type, url}`. Items whose
     * target is unpublished or deleted are omitted, as are disabled items.
     * Nested up to 3 levels via `children`. Cached; invalidated instantly on save.
     *
     * @tags CMS
     *
     * @unauthenticated
     */
    public function show(string $slug): JsonResponse
    {
        $payload = $this->cache->remember("menus.{$slug}", function () use ($slug) {
            $menu = Menu::query()->where('slug', $slug)->first();

            if ($menu === null) {
                return null;
            }

            return [
                'name' => $menu->name,
                'slug' => $menu->slug,
                'items' => $this->trees->build($menu),
            ];
        });

        if ($payload === null) {
            return $this->error('Menu not found.', 404);
        }

        return $this->success($payload);
    }
}
