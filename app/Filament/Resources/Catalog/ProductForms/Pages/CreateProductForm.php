<?php

namespace App\Filament\Resources\Catalog\ProductForms\Pages;

use App\Actions\Catalog\CreateProductFormAction;
use App\Data\Catalog\ProductFormData;
use App\Filament\Resources\Catalog\ProductForms\ProductFormResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductForm extends CreateRecord
{
    protected static string $resource = ProductFormResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateProductFormAction::class)->execute(ProductFormData::validateAndCreate($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
