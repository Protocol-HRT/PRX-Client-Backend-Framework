<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\ProductFormData;
use App\Models\Catalog\ProductForm;

class CreateProductFormAction
{
    use Transacts;

    public function execute(ProductFormData $data): ProductForm
    {
        return $this->tx(function () use ($data) {
            return ProductForm::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'requires_volume' => $data->requires_volume,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_value' => $data->provider_value,
            ]);
        });
    }
}
