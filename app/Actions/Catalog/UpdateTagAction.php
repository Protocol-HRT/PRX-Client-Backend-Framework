<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\TagData;
use App\Models\Catalog\Tag;

class UpdateTagAction
{
    use Transacts;

    public function execute(Tag $tag, TagData $data): Tag
    {
        return $this->tx(function () use ($tag, $data) {
            $tag->update([
                'name' => $data->name,
                'slug' => $data->slug ?: Tag::generateUniqueSlug($data->name, $tag->id),
                'color' => $data->color,
                'is_visible' => $data->is_visible,
                'position' => $data->position,
            ]);

            return $tag->fresh();
        });
    }
}
