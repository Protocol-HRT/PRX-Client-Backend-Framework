<?php

namespace App\Filament\Resources\Catalog\AdministrationMethods\Pages;

use App\Actions\Catalog\UpdateAdministrationMethodAction;
use App\Data\Catalog\AdministrationMethodData;
use App\Filament\Resources\Catalog\AdministrationMethods\AdministrationMethodResource;
use App\Models\Catalog\AdministrationMethod;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAdministrationMethod extends EditRecord
{
    protected static string $resource = AdministrationMethodResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var AdministrationMethod $record */
        return app(UpdateAdministrationMethodAction::class)->execute($record, AdministrationMethodData::validateAndCreate($data));
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
