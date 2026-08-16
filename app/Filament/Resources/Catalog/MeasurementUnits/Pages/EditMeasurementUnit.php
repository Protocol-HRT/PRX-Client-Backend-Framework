<?php

namespace App\Filament\Resources\Catalog\MeasurementUnits\Pages;

use App\Actions\Catalog\UpdateMeasurementUnitAction;
use App\Data\Catalog\MeasurementUnitData;
use App\Filament\Resources\Catalog\MeasurementUnits\MeasurementUnitResource;
use App\Models\Catalog\MeasurementUnit;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMeasurementUnit extends EditRecord
{
    protected static string $resource = MeasurementUnitResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MeasurementUnit $record */
        return app(UpdateMeasurementUnitAction::class)->execute($record, MeasurementUnitData::validateAndCreate($data));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
