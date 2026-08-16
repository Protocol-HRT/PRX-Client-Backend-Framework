<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\MeasurementUnitData;
use App\Models\Catalog\MeasurementUnit;

class UpdateMeasurementUnitAction
{
    use Transacts;

    public function execute(MeasurementUnit $measurementUnit, MeasurementUnitData $data): MeasurementUnit
    {
        return $this->tx(function () use ($measurementUnit, $data) {
            $measurementUnit->update([
                'name' => $data->name,
                'abbreviation' => $data->abbreviation,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_value' => $data->provider_value,
            ]);

            return $measurementUnit->fresh();
        });
    }
}
