<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Enums\CatalogStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Catalog\Category;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductClass;
use App\Models\Catalog\ProductForm;
use App\Models\Catalog\ProductType;
use App\Models\Catalog\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/catalog/facets
 */
class FacetController extends ApiController
{
    /**
     * Get catalog filter facets.
     *
     * Returns the option lists a product-listing filter UI needs: categories,
     * classes, types, forms, and ingredients (each with published-product
     * counts), the price bounds across published products, and availability
     * counts. Facet values with zero published products are omitted.
     *
     * @tags Catalog
     *
     * @unauthenticated
     */
    public function index(): JsonResponse
    {
        $published = fn (Builder $q) => $q->where('status', CatalogStatus::Published);

        $facet = fn ($collection) => $collection
            ->filter(fn ($row) => $row->products_count > 0)
            ->map(fn ($row) => [
                'name' => $row->name,
                'slug' => $row->slug,
                'count' => $row->products_count,
            ])->values()->all();

        $priceBounds = Product::query()
            ->where('status', CatalogStatus::Published)
            ->selectRaw('MIN(COALESCE(sale_price, retail_price) + 0) as min_price, MAX(COALESCE(sale_price, retail_price) + 0) as max_price')
            ->first();

        return $this->success([
            'categories' => $facet(Category::query()
                ->where('is_visible', true)
                ->orderBy('position')
                ->withCount(['products' => $published])
                ->get()),
            'classes' => $facet(ProductClass::query()
                ->active()
                ->orderBy('position')
                ->withCount(['products' => $published])
                ->get()),
            'types' => $facet(ProductType::query()
                ->active()
                ->orderBy('position')
                ->withCount(['products' => $published])
                ->get()),
            'forms' => $facet(ProductForm::query()
                ->active()
                ->orderBy('position')
                ->withCount(['products' => $published])
                ->get()),
            'ingredients' => $facet(Ingredient::query()
                ->active()
                ->orderBy('position')
                ->withCount(['products' => $published])
                ->get()),
            'tags' => $facet(Tag::query()
                ->where('is_visible', true)
                ->orderBy('position')
                ->withCount(['products' => $published])
                ->get()),
            'price' => [
                'min' => $priceBounds?->min_price !== null ? (float) $priceBounds->min_price : null,
                'max' => $priceBounds?->max_price !== null ? (float) $priceBounds->max_price : null,
                'currency' => 'USD',
            ],
            'availability' => [
                'in_stock' => Product::query()->where('status', CatalogStatus::Published)->where('is_in_stock', true)->count(),
                'out_of_stock' => Product::query()->where('status', CatalogStatus::Published)->where('is_in_stock', false)->count(),
            ],
        ]);
    }
}
