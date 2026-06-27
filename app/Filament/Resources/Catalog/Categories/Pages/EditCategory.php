<?php

namespace App\Filament\Resources\Catalog\Categories\Pages;

use App\Actions\Catalog\UpdateCategoryAction;
use App\Data\Catalog\CategoryData;
use App\Filament\Resources\Catalog\Categories\CategoryResource;
use App\Models\Catalog\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Category $record */
        return app(UpdateCategoryAction::class)->execute($record, CategoryData::validateAndCreate($data));
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
