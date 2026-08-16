<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\MeasurementUnitData;
use App\Models\Catalog\MeasurementUnit;

class CreateMeasurementUnitAction
{
    use Transacts;

    public function execute(MeasurementUnitData $data): MeasurementUnit
    {
        return $this->tx(function () use ($data) {
            return MeasurementUnit::create([
                'name' => $data->name,
                'abbreviation' => $data->abbreviation,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_value' => $data->provider_value,
            ]);
        });
    }
}
