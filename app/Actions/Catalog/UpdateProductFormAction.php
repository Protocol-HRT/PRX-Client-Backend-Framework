<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\ProductFormData;
use App\Models\Catalog\ProductForm;

class UpdateProductFormAction
{
    use Transacts;

    public function execute(ProductForm $productForm, ProductFormData $data): ProductForm
    {
        return $this->tx(function () use ($productForm, $data) {
            $productForm->update([
                'name' => $data->name,
                'slug' => $data->slug ?: $productForm->slug,
                'description' => $data->description,
                'requires_volume' => $data->requires_volume,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_value' => $data->provider_value,
            ]);

            return $productForm->fresh();
        });
    }
}
