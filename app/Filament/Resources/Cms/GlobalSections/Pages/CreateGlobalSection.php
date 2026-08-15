<?php

namespace App\Filament\Resources\Cms\GlobalSections\Pages;

use App\Actions\Cms\CreateGlobalSectionAction;
use App\Data\Cms\GlobalSectionData;
use App\Filament\Resources\Cms\GlobalSections\GlobalSectionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateGlobalSection extends CreateRecord
{
    protected static string $resource = GlobalSectionResource::class;

    /**
     * Route creation through our action layer so the architecture rule
     * (DTO → Action → DB::transaction) holds even from inside Filament.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateGlobalSectionAction::class)->execute(
                GlobalSectionData::validateAndCreate($data)
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not create global block')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
