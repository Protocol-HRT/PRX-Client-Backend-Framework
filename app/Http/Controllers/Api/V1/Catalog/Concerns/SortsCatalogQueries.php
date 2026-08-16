<?php

namespace App\Http\Controllers\Api\V1\Catalog\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared whitelist-based `sort` query-param handling for catalog index
 * endpoints. Price sorting uses the effective price (sale over retail).
 */
trait SortsCatalogQueries
{
    private function applyCatalogSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'name' => $query->orderBy('name'),
            '-name' => $query->orderByDesc('name'),
            'price' => $query->orderByRaw('COALESCE(sale_price, retail_price) + 0 asc'),
            '-price' => $query->orderByRaw('COALESCE(sale_price, retail_price) + 0 desc'),
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            default => $query->orderBy('position')->orderBy('name'),
        };
    }
}
