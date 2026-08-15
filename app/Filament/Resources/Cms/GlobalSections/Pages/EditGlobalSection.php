<?php

namespace App\Filament\Resources\Cms\GlobalSections\Pages;

use App\Actions\Cms\DeleteGlobalSectionAction;
use App\Actions\Cms\UpdateGlobalSectionAction;
use App\Data\Cms\GlobalSectionData;
use App\Filament\Resources\Cms\GlobalSections\GlobalSectionResource;
use App\Models\Cms\GlobalSection;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class EditGlobalSection extends EditRecord
{
    protected static string $resource = GlobalSectionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var GlobalSection $record */
        // Slug and type are disabled on edit so they are not dehydrated; feed
        // the stored values back in so the DTO validates. The action never
        // updates either of them.
        $data['slug'] = $record->slug;
        $data['type'] = $record->type;

        try {
            return app(UpdateGlobalSectionAction::class)->execute(
                $record,
                GlobalSectionData::validateAndCreate($data)
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not save global block')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (GlobalSection $record): bool {
                    try {
                        app(DeleteGlobalSectionAction::class)->execute($record);
                    } catch (RuntimeException $e) {
                        Notification::make()
                            ->title('Cannot delete global block')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return false;
                    }

                    return true;
                }),
        ];
    }
}
