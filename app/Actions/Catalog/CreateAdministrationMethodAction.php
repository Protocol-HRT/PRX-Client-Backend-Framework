<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\AdministrationMethodData;
use App\Models\Catalog\AdministrationMethod;

class CreateAdministrationMethodAction
{
    use Transacts;

    public function execute(AdministrationMethodData $data): AdministrationMethod
    {
        return $this->tx(function () use ($data) {
            return AdministrationMethod::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'abbreviation' => $data->abbreviation,
                'description' => $data->description,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_value' => $data->provider_value,
            ]);
        });
    }
}
