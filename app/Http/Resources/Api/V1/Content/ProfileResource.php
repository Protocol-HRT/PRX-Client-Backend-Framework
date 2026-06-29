<?php

namespace App\Http\Resources\Api\V1\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->title,
            'credentials' => $this->credentials,
            'bio' => $this->bio,
            'image_url' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            'is_featured' => $this->is_featured,
            'links' => $this->links,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
