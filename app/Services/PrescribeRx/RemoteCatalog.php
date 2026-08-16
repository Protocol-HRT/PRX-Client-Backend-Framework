<?php

namespace App\Services\PrescribeRx;

use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached view of the remote PRX catalog for the admin matching/mapping UI.
 *
 * Remote product/package lists are cached briefly so browsing, searching,
 * and suggestion scoring don't re-hit the PRX API on every table
 * interaction. Suggestions are scored by name similarity with an exact-SKU
 * short-circuit.
 */
class RemoteCatalog
{
    private const CACHE_TTL_SECONDS = 900;

    public function __construct(private readonly Client $prx) {}

    /** @return Collection<int, array<string, mixed>> */
    public function products(): Collection
    {
        return collect(Cache::remember(
            'prx.remote-catalog.products',
            self::CACHE_TTL_SECONDS,
            fn () => $this->prx->listAllPrxProducts(),
        ));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function packages(): Collection
    {
        return collect(Cache::remember(
            'prx.remote-catalog.packages',
            self::CACHE_TTL_SECONDS,
            fn () => $this->prx->listAllPrxPackages(),
        ));
    }

    public function refresh(): void
    {
        Cache::forget('prx.remote-catalog.products');
        Cache::forget('prx.remote-catalog.packages');
    }

    /**
     * Remote products ranked against a local product by SKU/name similarity.
     *
     * @return Collection<int, array<string, mixed>> remote rows with an added `match_score` (0–100)
     */
    public function suggestionsForProduct(Product $product, int $limit = 5): Collection
    {
        return $this->rankSuggestions($this->products(), $product->name, $product->provider_product_sku, $limit);
    }

    /**
     * Remote packages ranked against a local package by SKU/name similarity.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function suggestionsForPackage(Package $package, int $limit = 5): Collection
    {
        return $this->rankSuggestions($this->packages(), $package->name, $package->provider_package_sku, $limit);
    }

    /** @return array<string, array{id: int, name: string}> remote provider uuid → local row, for "already mapped" indicators */
    public function mappedProductIndex(): array
    {
        return Product::query()
            ->whereNotNull('provider_product_id')
            ->get(['id', 'name', 'provider_product_id'])
            ->keyBy('provider_product_id')
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }

    /** @return array<string, array{id: int, name: string}> */
    public function mappedPackageIndex(): array
    {
        return Package::query()
            ->whereNotNull('provider_package_id')
            ->get(['id', 'name', 'provider_package_id'])
            ->keyBy('provider_package_id')
            ->map(fn (Package $p) => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $remote
     * @return Collection<int, array<string, mixed>>
     */
    private function rankSuggestions(Collection $remote, string $localName, ?string $localSku, int $limit): Collection
    {
        return $remote
            ->map(function (array $row) use ($localName, $localSku): array {
                $row['match_score'] = $this->score($localName, $localSku, $row);

                return $row;
            })
            ->sortByDesc('match_score')
            ->take($limit)
            ->values();
    }

    private function score(string $localName, ?string $localSku, array $remote): int
    {
        if ($localSku !== null && isset($remote['sku']) && strcasecmp($localSku, (string) $remote['sku']) === 0) {
            return 100;
        }

        $local = mb_strtolower(trim($localName));
        $candidate = mb_strtolower(trim((string) ($remote['name'] ?? '')));

        if ($local === '' || $candidate === '') {
            return 0;
        }

        if ($local === $candidate) {
            return 100;
        }

        similar_text($local, $candidate, $percent);

        return (int) round($percent);
    }
}
