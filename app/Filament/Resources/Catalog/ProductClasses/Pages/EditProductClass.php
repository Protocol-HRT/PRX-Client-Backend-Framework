<?php

namespace App\Filament\Resources\Catalog\ProductClasses\Pages;

use App\Actions\Catalog\UpdateProductClassAction;
use App\Data\Catalog\ProductClassData;
use App\Filament\Resources\Catalog\ProductClasses\ProductClassResource;
use App\Models\Catalog\ProductClass;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProductClass extends EditRecord
{
    protected static string $resource = ProductClassResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ProductClass $record */
        return app(UpdateProductClassAction::class)->execute($record, ProductClassData::validateAndCreate($data));
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
