<?php

namespace App\Filament\Resources\Catalog\Ingredients\Pages;

use App\Filament\Resources\Catalog\Ingredients\IngredientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIngredients extends ListRecords
{
    protected static string $resource = IngredientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
