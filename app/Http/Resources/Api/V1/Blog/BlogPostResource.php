<?php

namespace App\Http\Resources\Api\V1\Blog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when(
                $request->routeIs('api.v1.blog.posts.show'),
                $this->content
            ),
            'hero_image_url' => $this->hero_image_path ? Storage::url($this->hero_image_path) : null,
            'gallery' => collect($this->gallery ?? [])->map(fn ($p) => Storage::url($p))->values()->all(),
            'status' => $this->status->value,
            'featured' => (bool) $this->featured,
            'published_at' => $this->published_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'read_time_minutes' => $this->read_time_minutes !== null ? (int) $this->read_time_minutes : null,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'categories' => BlogCategoryResource::collection($this->whenLoaded('categories')),
            'tags' => BlogTagResource::collection($this->whenLoaded('tags')),
            'seo' => $this->when($request->routeIs('api.v1.blog.posts.show'), [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'og_image_url' => $this->og_image_path ? Storage::url($this->og_image_path) : null,
            ]),
        ];
    }
}
