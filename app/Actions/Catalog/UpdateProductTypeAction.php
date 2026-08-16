<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\ProductTypeData;
use App\Models\Catalog\ProductType;

class UpdateProductTypeAction
{
    use Transacts;

    public function execute(ProductType $productType, ProductTypeData $data): ProductType
    {
        return $this->tx(function () use ($productType, $data) {
            $productType->update([
                'product_class_id' => $data->product_class_id,
                'name' => $data->name,
                'slug' => $data->slug ?: $productType->slug,
                'short_name' => $data->short_name,
                'description' => $data->description,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_product_type_id' => $data->provider_product_type_id,
            ]);

            return $productType->fresh();
        });
    }
}
