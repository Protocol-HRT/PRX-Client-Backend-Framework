<?php

namespace App\Filament\Resources\Catalog\Categories\Pages;

use App\Actions\Catalog\CreateCategoryAction;
use App\Data\Catalog\CategoryData;
use App\Filament\Resources\Catalog\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateCategoryAction::class)->execute(
            CategoryData::validateAndCreate($data)
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
