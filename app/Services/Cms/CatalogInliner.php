<?php

namespace App\Services\Cms;

use App\Http\Resources\Api\V1\Catalog\PackageResource;
use App\Http\Resources\Api\V1\Catalog\ProductResource;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;

/**
 * Inlines full catalog card data into section payloads so the frontend
 * renders product sliders/grids/callouts from the page fetch alone.
 *
 * Only published rows are ever emitted; manual selections preserve the
 * admin's chosen order and silently drop rows that were since unpublished.
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

        return ProductResource::collection($products)->resolve();
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

        return ProductResource::collection($query->get())->resolve();
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

        return PackageResource::collection($packages)->resolve();
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
}
