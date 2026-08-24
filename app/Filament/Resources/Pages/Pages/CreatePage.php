<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\Pages\CreatePageAction;
use App\Data\Pages\PageData;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /**
     * Route creation through our action layer so the architecture rule
     * (DTO → Action → DB::transaction) holds even from inside Filament.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePageAction::class)->execute(
            PageData::validateAndCreate($data)
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
