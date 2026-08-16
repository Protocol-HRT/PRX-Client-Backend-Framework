<?php

namespace App\Filament\Resources\Catalog\ProductTypes\Pages;

use App\Actions\Catalog\CreateProductTypeAction;
use App\Data\Catalog\ProductTypeData;
use App\Filament\Resources\Catalog\ProductTypes\ProductTypeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductType extends CreateRecord
{
    protected static string $resource = ProductTypeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateProductTypeAction::class)->execute(ProductTypeData::validateAndCreate($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
