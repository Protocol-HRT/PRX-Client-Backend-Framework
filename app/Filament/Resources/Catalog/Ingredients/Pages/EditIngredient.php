<?php

namespace App\Filament\Resources\Catalog\Ingredients\Pages;

use App\Actions\Catalog\UpdateIngredientAction;
use App\Data\Catalog\IngredientData;
use App\Filament\Resources\Catalog\Ingredients\IngredientResource;
use App\Models\Catalog\Ingredient;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditIngredient extends EditRecord
{
    protected static string $resource = IngredientResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Ingredient $record */
        return app(UpdateIngredientAction::class)->execute($record, IngredientData::validateAndCreate($data));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
