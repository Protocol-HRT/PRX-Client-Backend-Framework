<?php

namespace App\Http\Resources\Api\V1\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PackageResource extends JsonResource
{
    use Concerns\NormalizesDetailSections;

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
            'description' => $this->description,
            'hero_image_url' => $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null,
            'banner_image_url' => $this->banner_image_path ? Storage::disk('public')->url($this->banner_image_path) : null,
            'gallery' => collect($this->gallery ?? [])->map(fn ($p) => Storage::disk('public')->url($p))->values()->all(),
            'status' => $this->status->value,
            'badge_text' => $this->badge_text,
            'highlights' => $highlights,
            'is_featured' => (bool) $this->is_featured,
            'is_in_stock' => (bool) $this->is_in_stock,
            'is_on_sale' => (bool) ($this->relationLoaded('plans') && $this->plans->contains(fn ($p) => $p->sale_price !== null)),
            'requires_lab' => (bool) $this->requires_lab,
            'sort_order' => $this->position,
            'price_range' => $this->when(
                $this->relationLoaded('plans') && $this->plans->isNotEmpty(),
                fn () => $this->buildPriceRange()
            ),
            'detail_sections' => $this->when(
                $request->routeIs('api.v1.catalog.packages.show'),
                fn () => $this->normalizeDetailSections($this->detail_sections)
            ),
            'seo' => $this->when($request->routeIs('api.v1.catalog.packages.show'), [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'og_image_url' => $this->og_image_path ? Storage::disk('public')->url($this->og_image_path) : null,
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
            'related' => $this->when(
                $request->routeIs('api.v1.catalog.packages.show'),
                fn () => CatalogRelationItemResource::collection($this->relatedItems())->toArray($request)
            ),
            'pairs_with' => $this->when(
                $request->routeIs('api.v1.catalog.packages.show'),
                fn () => CatalogRelationItemResource::collection($this->pairsWithItems())->toArray($request)
            ),
        ];
    }

    /** @return array{from: float|null, to: float|null, currency: string} */
    private function buildPriceRange(): array
    {
        $prices = $this->plans
            ->filter(fn ($p) => $p->sale_price !== null || $p->retail_price !== null)
            ->map(fn ($p) => (float) ($p->sale_price ?? $p->retail_price));

        return [
            'from' => $prices->isNotEmpty() ? round((float) $prices->min(), 2) : null,
            'to' => $prices->isNotEmpty() ? round((float) $prices->max(), 2) : null,
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
