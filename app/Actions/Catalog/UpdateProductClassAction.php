<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\ProductClassData;
use App\Models\Catalog\ProductClass;

class UpdateProductClassAction
{
    use Transacts;

    public function execute(ProductClass $productClass, ProductClassData $data): ProductClass
    {
        return $this->tx(function () use ($productClass, $data) {
            $productClass->update([
                'name' => $data->name,
                'slug' => $data->slug ?: $productClass->slug,
                'short_name' => $data->short_name,
                'description' => $data->description,
                'icon' => $data->icon,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_product_class_id' => $data->provider_product_class_id,
            ]);

            return $productClass->fresh();
        });
    }
}
