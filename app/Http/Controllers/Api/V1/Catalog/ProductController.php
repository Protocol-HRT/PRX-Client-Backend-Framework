<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Catalog\ProductResource;
use App\Models\Catalog\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/catalog/products
 * GET /api/v1/catalog/products/{slug}
 */
class ProductController extends ApiController
{
    /**
     * List published catalog products.
     *
     * Returns a paginated list of published products with categories and tags.
     * Supports filtering by category slug, tag slug, featured flag, and name/subtitle search.
     *
     *
     * @tags Catalog
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 50);

        $products = Product::query()
            ->where('status', CatalogStatus::Published)
            ->with(['categories', 'tags'])
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'categories',
                fn ($q) => $q->where('slug', $request->string('category'))
            ))
            ->when($request->filled('tag'), fn ($q) => $q->whereHas(
                'tags',
                fn ($q) => $q->where('slug', $request->string('tag'))
            ))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->where('name', 'like', $term)->orWhere('subtitle', 'like', $term);
            }))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Get a published catalog product by slug.
     *
     * Includes categories, tags, and any packages that contain this product along with their plans.
     *
     * @tags Catalog
     *
     * @unauthenticated
     */
    public function show(Product $product): JsonResponse
    {
        abort_if($product->status !== CatalogStatus::Published, 404);

        $product->load(['categories', 'tags', 'packages.plans']);

        return $this->success((new ProductResource($product))->toArray(request()));
    }
}
