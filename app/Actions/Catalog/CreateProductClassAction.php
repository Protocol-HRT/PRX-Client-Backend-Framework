<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\ProductClassData;
use App\Models\Catalog\ProductClass;

class CreateProductClassAction
{
    use Transacts;

    public function execute(ProductClassData $data): ProductClass
    {
        return $this->tx(function () use ($data) {
            return ProductClass::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'short_name' => $data->short_name,
                'description' => $data->description,
                'icon' => $data->icon,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_product_class_id' => $data->provider_product_class_id,
            ]);
        });
    }
}
