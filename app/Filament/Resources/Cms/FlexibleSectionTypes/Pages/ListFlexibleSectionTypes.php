<?php

namespace App\Filament\Resources\Cms\FlexibleSectionTypes\Pages;

use App\Filament\Resources\Cms\FlexibleSectionTypes\FlexibleSectionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlexibleSectionTypes extends ListRecords
{
    protected static string $resource = FlexibleSectionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
