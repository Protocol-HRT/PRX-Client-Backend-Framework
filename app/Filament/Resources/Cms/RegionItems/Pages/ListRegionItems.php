<?php

namespace App\Filament\Resources\Cms\RegionItems\Pages;

use App\Filament\Resources\Cms\RegionItems\RegionItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegionItems extends ListRecords
{
    protected static string $resource = RegionItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
