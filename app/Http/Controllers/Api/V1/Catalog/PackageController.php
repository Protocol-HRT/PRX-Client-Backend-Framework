<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Catalog\PackageResource;
use App\Models\Catalog\Package;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/catalog/packages
 * GET /api/v1/catalog/packages/{slug}
 */
class PackageController extends ApiController
{
    use Concerns\SortsCatalogQueries;

    /**
     * List published catalog packages.
     *
     * Returns a paginated list of published packages with their plans, categories, and tags.
     *
     * @tags Catalog
     *
     * @unauthenticated
     */
    #[QueryParameter('search', 'Filter by package name or subtitle.', type: 'string', example: 'hormone')]
    #[QueryParameter('price_min', 'Filter packages with at least one plan priced at or above this amount (USD).', type: 'float', infer: false, example: 50)]
    #[QueryParameter('price_max', 'Filter packages with at least one plan priced at or below this amount (USD).', type: 'float', infer: false, example: 300)]
    #[QueryParameter('sort', 'Sort order: position (default), name, -name, price, -price, newest, oldest.', type: 'string', example: '-price')]
    #[QueryParameter('per_page', 'Results per page (1–50, default 15).', type: 'integer', example: 15)]
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
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('is_in_stock', true))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->where('name', 'like', $term)->orWhere('subtitle', 'like', $term);
            }))
            ->when(
                $request->filled('price_min') || $request->filled('price_max'),
                function ($q) use ($request): void {
                    $min = $request->filled('price_min') ? (float) $request->input('price_min') : null;
                    $max = $request->filled('price_max') ? (float) $request->input('price_max') : null;

                    $q->whereHas('plans', function ($q) use ($min, $max): void {
                        $q->where('status', CatalogStatus::Published)
                            ->where(function ($q) use ($min, $max): void {
                                if ($min !== null) {
                                    // `? + 0` coerces the TEXT-bound float to REAL for correct SQLite comparison
                                    $q->whereRaw('COALESCE(sale_price, retail_price) >= ? + 0', [$min]);
                                }
                                if ($max !== null) {
                                    $q->whereRaw('COALESCE(sale_price, retail_price) <= ? + 0', [$max]);
                                }
                            });
                    });
                }
            )
            ->tap(fn ($q) => $this->applyCatalogSort($q, $request->input('sort')))
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
            'faqs' => fn ($q) => $q->where('is_published', true),
            'faqs.category',
            'approvedReviews',
            'sections.globalSection',
        ]);

        return $this->success((new PackageResource($package))->toArray(request()));
    }
}
