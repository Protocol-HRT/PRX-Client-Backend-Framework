<?php

namespace App\Filament\Resources\Catalog\AdministrationMethods\Pages;

use App\Actions\Catalog\CreateAdministrationMethodAction;
use App\Data\Catalog\AdministrationMethodData;
use App\Filament\Resources\Catalog\AdministrationMethods\AdministrationMethodResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAdministrationMethod extends CreateRecord
{
    protected static string $resource = AdministrationMethodResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateAdministrationMethodAction::class)->execute(AdministrationMethodData::validateAndCreate($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
