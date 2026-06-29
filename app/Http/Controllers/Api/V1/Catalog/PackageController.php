<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Catalog\PackageResource;
use App\Models\Catalog\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/catalog/packages
 * GET /api/v1/catalog/packages/{slug}
 */
class PackageController extends ApiController
{
    /**
     * List published catalog packages.
     *
     * Returns a paginated list of published packages with their plans, categories, and tags.
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

        $packages = Package::query()
            ->where('status', CatalogStatus::Published)
            ->with([
                'plans' => fn ($q) => $q->where('status', CatalogStatus::Published)->orderBy('position'),
                'categories',
                'tags',
            ])
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

        return PackageResource::collection($packages);
    }

    /**
     * Get a published catalog package by slug.
     *
     * Includes published plans, included products, categories, and tags.
     *
     * @tags Catalog
     *
     * @unauthenticated
     */
    public function show(Package $package): JsonResponse
    {
        abort_if($package->status !== CatalogStatus::Published, 404);

        $package->load([
            'plans' => fn ($q) => $q->where('status', CatalogStatus::Published)->orderBy('position'),
            'products' => fn ($q) => $q->where('status', CatalogStatus::Published)->orderByPivot('sort_order'),
            'categories',
            'tags',
        ]);

        return $this->success((new PackageResource($package))->toArray(request()));
    }
}
