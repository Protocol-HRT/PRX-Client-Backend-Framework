<?php

namespace App\Filament\Resources\Cms\FlexibleSectionTypes\Pages;

use App\Actions\Cms\CreateFlexibleSectionTypeAction;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Filament\Resources\Cms\FlexibleSectionTypes\FlexibleSectionTypeResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateFlexibleSectionType extends CreateRecord
{
    protected static string $resource = FlexibleSectionTypeResource::class;

    /**
     * Route creation through our action layer so the architecture rule
     * (DTO → Action → DB::transaction) holds even from inside Filament.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateFlexibleSectionTypeAction::class)->execute(
                FlexibleSectionTypeData::validateAndCreate($data)
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not create section type')
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
