<?php

namespace App\Http\Resources\Api\V1\Kb;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Public shape of a health goal — what the intake quiz renders as a choice.
 *
 * `prompt` falls back to `name` rather than being emitted null, because the
 * quiz has to put SOMETHING on the card and a blank option is worse than a
 * terse one. The fallback lives here rather than in the frontend so every
 * consumer gets the same answer.
 *
 * What is deliberately NOT here: the ingredient, product and compound
 * mappings. Those are how a recommendation is DERIVED, and deriving happens
 * server-side — shipping the weighted edges to the browser would hand a
 * competitor the mapping that took a clinician to build, and would let a
 * visitor read which products they were about to be steered toward.
 */
class HealthGoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'prompt' => $this->prompt ?: $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'parent_slug' => $this->whenLoaded('parent', fn (): ?string => $this->parent?->slug),
            'sort_order' => $this->position,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
