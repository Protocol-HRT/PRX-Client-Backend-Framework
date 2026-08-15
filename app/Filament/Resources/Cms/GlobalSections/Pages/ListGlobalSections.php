<?php

namespace App\Filament\Resources\Cms\GlobalSections\Pages;

use App\Filament\Resources\Cms\GlobalSections\GlobalSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGlobalSections extends ListRecords
{
    protected static string $resource = GlobalSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
