<?php

namespace App\Http\Resources\Api\V1\Cms;

use App\Services\Cms\MediaResolver;
use App\Services\Cms\SectionDataTransformer;
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
            'title_banner' => $this->resolveTitleBanner(),
            'seo' => [
                'title' => $this->meta_title ?? $this->title,
                'description' => $this->meta_description,
                'og_image_url' => $this->og_image_path ? Storage::url($this->og_image_path) : null,
                'noindex' => (bool) $this->noindex,
            ],
        ];

        if ($this->includeSections) {
            $base['sections'] = $this->whenLoaded('sections', function () {
                return app(SectionDataTransformer::class)->transform($this->sections);
            }, []);
        }

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTitleBanner(): ?array
    {
        $banner = $this->title_banner;

        if (! is_array($banner) || ! ($banner['enabled'] ?? false)) {
            return null;
        }

        return [
            'title' => filled($banner['title_override'] ?? null) ? $banner['title_override'] : $this->title,
            'subtitle' => $banner['subtitle'] ?? null,
            'intro_text' => $banner['intro_text'] ?? null,
            'show_breadcrumbs' => (bool) ($banner['show_breadcrumbs'] ?? true),
            'background_image' => app(MediaResolver::class)->resolve($banner['background_image'] ?? null),
        ];
    }
}
