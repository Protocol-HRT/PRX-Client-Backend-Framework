<?php

namespace App\Http\Resources\Api\V1\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'hero_image_url' => $this->hero_image_path ? Storage::url($this->hero_image_path) : null,
            'banner_image_url' => $this->banner_image_path ? Storage::url($this->banner_image_path) : null,
            'gallery' => collect($this->gallery ?? [])->map(fn ($p) => Storage::url($p))->values(),
            'status' => $this->status->value,
            'badge_text' => $this->badge_text,
            'highlights' => $this->normalizeHighlights($this->highlights),
            'is_featured' => $this->is_featured,
            'requires_lab' => $this->requires_lab,
            'sort_order' => $this->position,
            'price_range' => $this->when(
                $this->relationLoaded('plans') && $this->plans->isNotEmpty(),
                fn () => $this->buildPriceRange()
            ),
            'seo' => $this->when($request->routeIs('api.v1.catalog.packages.show'), [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'og_image_url' => $this->og_image_path ? Storage::url($this->og_image_path) : null,
            ]),
            'provider' => [
                'package_id' => $this->provider_package_id,
                'package_sku' => $this->provider_package_sku,
                'encounter_type_id' => $this->provider_encounter_type_id,
            ],
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'plans' => PlanResource::collection($this->whenLoaded('plans')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }

    /** @return array{from: string|null, to: string|null, currency: string} */
    private function buildPriceRange(): array
    {
        $prices = $this->plans
            ->filter(fn ($p) => $p->sale_price !== null || $p->retail_price !== null)
            ->map(fn ($p) => (float) ($p->sale_price ?? $p->retail_price));

        return [
            'from' => $prices->isNotEmpty() ? number_format($prices->min(), 2) : null,
            'to' => $prices->isNotEmpty() ? number_format($prices->max(), 2) : null,
            'currency' => 'USD',
        ];
    }

    /**
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
