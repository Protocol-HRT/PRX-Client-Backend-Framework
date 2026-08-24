<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\TagData;
use App\Models\Catalog\Tag;

class CreateTagAction
{
    use Transacts;

    public function execute(TagData $data): Tag
    {
        return $this->tx(function () use ($data) {
            return Tag::create([
                'name' => $data->name,
                'slug' => $data->slug ?: Tag::generateUniqueSlug($data->name),
                'color' => $data->color,
                'is_visible' => $data->is_visible,
                'position' => $data->position,
            ]);
        });
    }
}
