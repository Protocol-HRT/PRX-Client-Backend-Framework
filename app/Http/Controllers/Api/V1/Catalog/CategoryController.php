<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Catalog\CategoryResource;
use App\Models\Catalog\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/catalog/categories
 * GET /api/v1/catalog/categories/{slug}
 */
class CategoryController extends ApiController
{
    /**
     * @return AnonymousResourceCollection
     *
     * Query params:
     *   ?tree=1    Return nested tree (children embedded). Default: flat list.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query()
            ->where('is_visible', true)
            ->orderBy('position')
            ->orderBy('name');

        if ($request->boolean('tree')) {
            // Top-level only; children eager-loaded recursively.
            $categories = $query
                ->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->where('is_visible', true)->orderBy('position')])
                ->get();
        } else {
            $categories = $query->with('parent')->get();
        }

        return CategoryResource::collection($categories);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        abort_if(! $category->is_visible, 404);

        $category->load(['parent', 'children' => fn ($q) => $q->where('is_visible', true)->orderBy('position')]);

        return $this->success((new CategoryResource($category))->toArray($request));
    }
}
