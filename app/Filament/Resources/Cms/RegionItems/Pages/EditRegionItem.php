<?php

namespace App\Filament\Resources\Cms\RegionItems\Pages;

use App\Filament\Resources\Cms\RegionItems\RegionItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRegionItem extends EditRecord
{
    protected static string $resource = RegionItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
