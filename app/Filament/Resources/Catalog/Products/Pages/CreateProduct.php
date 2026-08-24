<?php

namespace App\Filament\Resources\Catalog\Products\Pages;

use App\Actions\Catalog\CreateProductAction;
use App\Data\Catalog\ProductData;
use App\Filament\Resources\Catalog\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateProductAction::class)->execute(
            ProductData::validateAndCreate($data)
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
