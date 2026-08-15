<?php

namespace App\Filament\Resources\Cms\Menus\Pages;

use App\Actions\Cms\CreateMenuAction;
use App\Data\Cms\MenuData;
use App\Filament\Resources\Cms\Menus\MenuResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;

    /**
     * Route creation through our action layer so the architecture rule
     * (DTO → Action → DB::transaction) holds even from inside Filament.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateMenuAction::class)->execute(
                MenuData::validateAndCreate($data)
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not create menu')
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
