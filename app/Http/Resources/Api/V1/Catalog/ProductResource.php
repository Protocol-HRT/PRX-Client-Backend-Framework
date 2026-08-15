<?php

namespace App\Http\Resources\Api\V1\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<string> $highlights */
        $highlights = $this->normalizeHighlights($this->highlights);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'short_description' => $this->short_description,
            'description' => $this->when($request->routeIs('api.v1.catalog.products.show'), $this->description),
            'hero_image_url' => $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null,
            'gallery' => collect($this->gallery ?? [])->map(fn ($p) => Storage::disk('public')->url($p))->values()->all(),
            'status' => $this->status->value,
            'badge_text' => $this->badge_text,
            'highlights' => $highlights,
            'is_featured' => (bool) $this->is_featured,
            'is_in_stock' => (bool) $this->is_in_stock,
            'is_on_sale' => $this->sale_price !== null,
            'requires_lab' => (bool) $this->requires_lab,
            'sort_order' => $this->position,
            'price' => [
                'retail' => $this->retail_price !== null ? (float) $this->retail_price : null,
                'sale' => $this->sale_price !== null ? (float) $this->sale_price : null,
                'effective' => (float) ($this->sale_price ?? $this->retail_price),
                'suffix' => $this->price_suffix,
                'currency' => 'USD',
            ],
            'seo' => $this->when($request->routeIs('api.v1.catalog.products.show'), [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'og_image_url' => $this->og_image_path ? Storage::disk('public')->url($this->og_image_path) : null,
            ]),
            'provider' => [
                'product_id' => $this->provider_product_id,
                'product_sku' => $this->provider_product_sku,
                'encounter_type_id' => $this->provider_encounter_type_id,
            ],
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }

    /**
     * Normalizes the Filament Repeater format [{"item": "text"}] to a flat ["text"] array.
     *
     * @param  array<int, array<string, string>>|null  $highlights
     * @return list<string>
     */
    private function normalizeHighlights(?array $highlights): array
    {
        if (empty($highlights)) {
            return [];
        }

        return collect($highlights)
            ->pluck('item')
            ->filter()
            ->values()
            ->all();
    }
}
