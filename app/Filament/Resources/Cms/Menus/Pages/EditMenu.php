<?php

namespace App\Filament\Resources\Cms\Menus\Pages;

use App\Actions\Cms\DeleteMenuAction;
use App\Actions\Cms\UpdateMenuAction;
use App\Data\Cms\MenuData;
use App\Filament\Resources\Cms\Menus\MenuResource;
use App\Filament\Support\RelationTabs;
use App\Models\Cms\Menu;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    /**
     * Form tabs on top, relation managers as vertical tabs directly below
     * (instead of the default horizontal below-the-fold strip).
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            RelationTabs::make($this),
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Menu $record */
        // The slug field is disabled on edit so it is not dehydrated; feed the
        // stored value back in so the DTO validates. The action never updates it.
        $data['slug'] = $record->slug;

        try {
            return app(UpdateMenuAction::class)->execute(
                $record,
                MenuData::validateAndCreate($data)
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Could not save menu')
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
                ->modalDescription('Deleting a menu also deletes all of its items. This cannot be undone.')
                ->using(function (Menu $record): bool {
                    app(DeleteMenuAction::class)->execute($record);

                    return true;
                }),
        ];
    }
}
