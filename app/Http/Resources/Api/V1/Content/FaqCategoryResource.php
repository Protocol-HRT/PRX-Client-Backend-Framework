<?php

namespace App\Http\Resources\Api\V1\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'items' => FaqItemResource::collection($this->whenLoaded('publishedItems')),
        ];
    }
}
