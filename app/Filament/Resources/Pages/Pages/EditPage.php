<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\Pages\UpdatePageAction;
use App\Data\Pages\PageData;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        return app(UpdatePageAction::class)->execute(
            $record,
            PageData::validateAndCreate($data)
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View public page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Page $record) => url($record->slug))
                ->openUrlInNewTab()
                ->visible(fn (Page $record) => $record->isPublished()),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
