<?php

namespace App\Filament\Resources\Catalog\Ingredients\Pages;

use App\Actions\Catalog\CreateIngredientAction;
use App\Data\Catalog\IngredientData;
use App\Filament\Resources\Catalog\Ingredients\IngredientResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateIngredient extends CreateRecord
{
    protected static string $resource = IngredientResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateIngredientAction::class)->execute(IngredientData::validateAndCreate($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
