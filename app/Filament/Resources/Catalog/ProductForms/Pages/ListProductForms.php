<?php

namespace App\Filament\Resources\Catalog\ProductForms\Pages;

use App\Filament\Resources\Catalog\ProductForms\ProductFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductForms extends ListRecords
{
    protected static string $resource = ProductFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
