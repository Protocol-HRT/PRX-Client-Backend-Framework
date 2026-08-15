<?php

namespace App\Filament\Resources\Cms\FlexibleSectionTypes\Pages;

use App\Actions\Cms\DeleteFlexibleSectionTypeAction;
use App\Actions\Cms\UpdateFlexibleSectionTypeAction;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Filament\Resources\Cms\FlexibleSectionTypes\FlexibleSectionTypeResource;
use App\Models\Cms\FlexibleSectionType;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class EditFlexibleSectionType extends EditRecord
{
    protected static string $resource = FlexibleSectionTypeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var FlexibleSectionType $record */
        // The slug field is disabled on edit so it is not dehydrated; feed the
        // stored value back in so the DTO validates. The action never updates it.
        $data['slug'] = $record->slug;

        try {
            return app(UpdateFlexibleSectionTypeAction::class)->execute(
                $record,
                FlexibleSectionTypeData::validateAndCreate($data)
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not save section type')
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
                ->using(function (FlexibleSectionType $record): bool {
                    try {
                        app(DeleteFlexibleSectionTypeAction::class)->execute($record);
                    } catch (RuntimeException $e) {
                        Notification::make()
                            ->title('Cannot delete section type')
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
