<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\CategoryData;
use App\Models\Catalog\Category;

class UpdateCategoryAction
{
    use Transacts;

    public function execute(Category $category, CategoryData $data): Category
    {
        return $this->tx(function () use ($category, $data) {
            $category->update([
                'parent_id' => $data->parent_id,
                'name' => $data->name,
                'slug' => $data->slug ?: Category::generateUniqueSlug($data->name, $category->id),
                'description' => $data->description,
                'hero_image_path' => $data->hero_image_path,
                'icon' => $data->icon,
                'is_visible' => $data->is_visible,
                'position' => $data->position,
                'meta_title' => $data->meta_title,
                'meta_description' => $data->meta_description,
            ]);

            return $category->fresh();
        });
    }
}
