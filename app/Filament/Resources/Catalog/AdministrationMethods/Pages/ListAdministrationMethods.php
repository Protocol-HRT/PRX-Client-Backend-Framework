<?php

namespace App\Filament\Resources\Catalog\AdministrationMethods\Pages;

use App\Filament\Resources\Catalog\AdministrationMethods\AdministrationMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdministrationMethods extends ListRecords
{
    protected static string $resource = AdministrationMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
