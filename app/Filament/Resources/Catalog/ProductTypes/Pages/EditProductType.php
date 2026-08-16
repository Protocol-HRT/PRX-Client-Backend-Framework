<?php

namespace App\Filament\Resources\Catalog\ProductTypes\Pages;

use App\Actions\Catalog\UpdateProductTypeAction;
use App\Data\Catalog\ProductTypeData;
use App\Filament\Resources\Catalog\ProductTypes\ProductTypeResource;
use App\Models\Catalog\ProductType;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProductType extends EditRecord
{
    protected static string $resource = ProductTypeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ProductType $record */
        return app(UpdateProductTypeAction::class)->execute($record, ProductTypeData::validateAndCreate($data));
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
