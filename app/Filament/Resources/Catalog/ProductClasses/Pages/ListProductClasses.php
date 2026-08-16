<?php

namespace App\Filament\Resources\Catalog\ProductClasses\Pages;

use App\Filament\Resources\Catalog\ProductClasses\ProductClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductClasses extends ListRecords
{
    protected static string $resource = ProductClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
