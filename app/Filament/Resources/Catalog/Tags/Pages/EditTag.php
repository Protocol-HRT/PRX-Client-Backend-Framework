<?php

namespace App\Filament\Resources\Catalog\Tags\Pages;

use App\Actions\Catalog\UpdateTagAction;
use App\Data\Catalog\TagData;
use App\Filament\Resources\Catalog\Tags\TagResource;
use App\Models\Catalog\Tag;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Tag $record */
        return app(UpdateTagAction::class)->execute($record, TagData::validateAndCreate($data));
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
