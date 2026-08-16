<?php

namespace App\Filament\Resources\Catalog\ProductForms\Pages;

use App\Actions\Catalog\UpdateProductFormAction;
use App\Data\Catalog\ProductFormData;
use App\Filament\Resources\Catalog\ProductForms\ProductFormResource;
use App\Models\Catalog\ProductForm as ProductFormModel;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProductForm extends EditRecord
{
    protected static string $resource = ProductFormResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ProductFormModel $record */
        return app(UpdateProductFormAction::class)->execute($record, ProductFormData::validateAndCreate($data));
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
