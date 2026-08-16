<?php

namespace App\Filament\Resources\Catalog\MeasurementUnits\Pages;

use App\Actions\Catalog\CreateMeasurementUnitAction;
use App\Data\Catalog\MeasurementUnitData;
use App\Filament\Resources\Catalog\MeasurementUnits\MeasurementUnitResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMeasurementUnit extends CreateRecord
{
    protected static string $resource = MeasurementUnitResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateMeasurementUnitAction::class)->execute(MeasurementUnitData::validateAndCreate($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
