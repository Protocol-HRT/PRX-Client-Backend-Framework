<?php

namespace App\Filament\Resources\Catalog\ProductClasses\Pages;

use App\Actions\Catalog\CreateProductClassAction;
use App\Data\Catalog\ProductClassData;
use App\Filament\Resources\Catalog\ProductClasses\ProductClassResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProductClass extends CreateRecord
{
    protected static string $resource = ProductClassResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateProductClassAction::class)->execute(ProductClassData::validateAndCreate($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
