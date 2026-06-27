<?php

namespace App\Http\Resources\Api\V1\Blog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BlogCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'hero_image_url' => $this->hero_image_path ? Storage::url($this->hero_image_path) : null,
            'is_visible' => $this->is_visible,
        ];
    }
}
