<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\AdministrationMethodData;
use App\Models\Catalog\AdministrationMethod;

class UpdateAdministrationMethodAction
{
    use Transacts;

    public function execute(AdministrationMethod $administrationMethod, AdministrationMethodData $data): AdministrationMethod
    {
        return $this->tx(function () use ($administrationMethod, $data) {
            $administrationMethod->update([
                'name' => $data->name,
                'slug' => $data->slug ?: $administrationMethod->slug,
                'abbreviation' => $data->abbreviation,
                'description' => $data->description,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_value' => $data->provider_value,
            ]);

            return $administrationMethod->fresh();
        });
    }
}
