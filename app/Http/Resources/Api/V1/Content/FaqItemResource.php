<?php

namespace App\Http\Resources\Api\V1\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'position' => $this->position,
            'category' => new FaqCategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
