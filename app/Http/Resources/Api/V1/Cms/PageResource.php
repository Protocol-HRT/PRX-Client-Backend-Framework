<?php

namespace App\Http\Resources\Api\V1\Cms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PageResource extends JsonResource
{
    private bool $includeSections = false;

    public function withSections(): static
    {
        $this->includeSections = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $base = [
            'title' => $this->title,
            'slug' => $this->slug,
            'seo' => [
                'title' => $this->meta_title ?? $this->title,
                'description' => $this->meta_description,
                'og_image_url' => $this->og_image_path ? Storage::url($this->og_image_path) : null,
                'noindex' => (bool) $this->noindex,
            ],
        ];

        if ($this->includeSections) {
            $base['sections'] = $this->whenLoaded('sections', function () {
                return $this->sections->map(fn ($section) => [
                    'type' => $section->type->value,
                    'data' => $section->data ?? [],
                ])->values()->all();
            }, []);
        }

        return $base;
    }
}
