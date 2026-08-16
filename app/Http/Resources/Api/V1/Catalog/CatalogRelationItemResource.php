<?php

namespace App\Http\Resources\Api\V1\Catalog;

use App\Models\Catalog\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Lightweight card reference for related / pairs-with catalog items.
 * The underlying resource may be a Product or a Package; `type` tells the
 * frontend which detail route to link to.
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
            'price' => [
                'retail' => $this->retail_price !== null ? (float) $this->retail_price : null,
                'sale' => $this->sale_price !== null ? (float) $this->sale_price : null,
                'effective' => (float) ($this->sale_price ?? $this->retail_price),
                'suffix' => $this->price_suffix,
                'currency' => 'USD',
            ],
        ];
    }
}
