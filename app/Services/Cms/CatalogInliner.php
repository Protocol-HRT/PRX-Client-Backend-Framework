<?php

namespace App\Services\Cms;

use App\Http\Resources\Api\V1\Catalog\CategoryResource;
use App\Http\Resources\Api\V1\Catalog\PackageResource;
use App\Http\Resources\Api\V1\Catalog\ProductResource;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;

/**
 * Inlines full catalog card data into section payloads so the frontend
 * renders product sliders/grids/callouts from the page fetch alone.
 *
 * Only published rows are ever emitted; manual selections preserve the
 * admin's chosen order and silently drop rows that were since unpublished.
 *
 * Payloads are fully serialized to plain arrays (not ->resolve(), which leaves
 * nested resource collections as objects): section data is cached by CmsCache,
 * and resource objects do not survive the serialize round-trip.
 */
class CatalogInliner
{
    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function productsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $products = Product::query()
            ->published()
            ->whereIn('id', $ids)
            ->with(['categories', 'tags'])
            ->get()
            ->sortBy(fn (Product $product): int => (int) array_search($product->id, $ids))
            ->values();

        return $this->toPlainArray(ProductResource::collection($products));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productsByMode(string $mode, ?int $categoryId, int $limit): array
    {
        $query = Product::query()
            ->published()
            ->with(['categories', 'tags'])
            ->limit(max(1, min($limit, 24)));

        match ($mode) {
            'featured' => $query->where('is_featured', true)->orderBy('position'),
            'newest' => $query->latest('created_at'),
            'category' => $query
                ->when($categoryId !== null, fn ($q) => $q->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId)))
                ->orderBy('position'),
            default => $query->orderBy('position'),
        };

        return $this->toPlainArray(ProductResource::collection($query->get()));
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function packagesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $packages = Package::query()
            ->published()
            ->whereIn('id', $ids)
            ->with(['plans', 'products', 'categories', 'tags'])
            ->get()
            ->sortBy(fn (Package $package): int => (int) array_search($package->id, $ids))
            ->values();

        return $this->toPlainArray(PackageResource::collection($packages));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packagesByMode(string $mode, ?int $categoryId, int $limit): array
    {
        $query = Package::query()
            ->published()
            ->with(['plans', 'products', 'categories', 'tags'])
            ->limit(max(1, min($limit, 24)));

        match ($mode) {
            'featured' => $query->where('is_featured', true)->orderBy('position'),
            'newest' => $query->latest('created_at'),
            'category' => $query
                ->when($categoryId !== null, fn ($q) => $q->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId)))
                ->orderBy('position'),
            default => $query->orderBy('position'),
        };

        return $this->toPlainArray(PackageResource::collection($query->get()));
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function categoriesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $categories = Category::query()
            ->where('is_visible', true)
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Category $category): int => (int) array_search($category->id, $ids))
            ->values();

        return $this->toPlainArray(CategoryResource::collection($categories));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visibleCategories(int $limit): array
    {
        $categories = Category::query()
            ->where('is_visible', true)
            ->orderBy('position')
            ->limit(max(1, min($limit, 24)))
            ->get();

        return $this->toPlainArray(CategoryResource::collection($categories));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function product(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }

        return $this->productsByIds([$id])[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function package(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }

        return $this->packagesByIds([$id])[0] ?? null;
    }

    /**
     * Serialize a resource collection through the JSON pipeline, resolving
     * nested resources exactly as an HTTP response would.
     *
     * @return list<array<string, mixed>>
     */
    private function toPlainArray(mixed $collection): array
    {
        return json_decode(json_encode($collection), true) ?? [];
    }
}
