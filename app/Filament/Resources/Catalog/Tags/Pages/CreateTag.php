<?php

namespace App\Filament\Resources\Catalog\Tags\Pages;

use App\Actions\Catalog\CreateTagAction;
use App\Data\Catalog\TagData;
use App\Filament\Resources\Catalog\Tags\TagResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTagAction::class)->execute(TagData::validateAndCreate($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
