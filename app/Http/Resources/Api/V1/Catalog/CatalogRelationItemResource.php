<?php

namespace App\Http\Resources\Api\V1\Catalog;

use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Lightweight card reference for related / pairs-with catalog items.
 * The underlying resource may be a Product or a Package; `type` tells the
 * frontend which detail route to link to.
 *
 * Packages carry no price columns of their own — their price comes from the
 * default (or first) published plan when the relation loader eager-loaded
 * plans. `effective` is null (not 0.00) when no price exists at all.
 */
class CatalogRelationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->resource instanceof Package ? 'package' : 'product',
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'badge_text' => $this->badge_text,
            'hero_image_url' => $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null,
            'is_in_stock' => (bool) $this->is_in_stock,
            'price' => $this->priceBlock(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function priceBlock(): array
    {
        $source = $this->resource;

        if ($source instanceof Package && $source->relationLoaded('plans')) {
            $source = $source->plans->firstWhere('is_default', true)
                ?? $source->plans->first()
                ?? $source;
        }

        $retail = $source->retail_price ?? null;
        $sale = $source->sale_price ?? null;
        $effective = $sale ?? $retail;

        $suffix = $source->price_suffix ?? null;
        if ($suffix === null && $source instanceof Plan) {
            $suffix = $source->billing_period?->suffix();
        }

        return [
            'retail' => $retail !== null ? (float) $retail : null,
            'sale' => $sale !== null ? (float) $sale : null,
            'effective' => $effective !== null ? (float) $effective : null,
            'suffix' => $suffix,
            'currency' => 'USD',
        ];
    }
}
