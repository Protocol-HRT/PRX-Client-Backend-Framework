<?php

namespace App\Filament\Resources\Cms\FlexibleSectionTypes\Tables;

use App\Actions\Cms\DeleteFlexibleSectionTypeAction;
use App\Actions\Cms\SetFlexibleSectionTypeArchivedAction;
use App\Models\Cms\FlexibleSectionType;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use RuntimeException;

class FlexibleSectionTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->badge()
                    ->copyable(),
                TextColumn::make('fields_count')
                    ->label('Fields')
                    ->state(fn (FlexibleSectionType $record): int => count($record->schema['fields'] ?? [])),
                TextColumn::make('usage')
                    ->label('In use')
                    ->state(fn (FlexibleSectionType $record): int => $record->usageCount()),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('archived_at')
                    ->label('Status')
                    ->badge()
                    ->state(fn (FlexibleSectionType $record): string => $record->isArchived() ? 'Archived' : 'Active')
                    ->color(fn (string $state): string => $state === 'Archived' ? 'gray' : 'success'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('enabled'),
                TernaryFilter::make('archived')
                    ->label('Archived')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('archived_at'),
                        false: fn ($query) => $query->whereNull('archived_at'),
                        blank: fn ($query) => $query,
                    )
                    ->default(false),
            ])
            ->recordActions([
                EditAction::make(),
                self::archiveAction(),
                self::restoreAction(),
                DeleteAction::make()
                    ->using(fn (FlexibleSectionType $record): bool => self::delete($record)),
            ]);
    }

    /**
     * Stash the type: gone from the section picker, existing sections keep
     * rendering. Restore brings it back for new use.
     */
    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->visible(fn (FlexibleSectionType $record): bool => ! $record->isArchived())
            ->requiresConfirmation()
            ->modalDescription('Archiving removes this type from the section picker so nothing new can be built with it. Existing sections keep rendering. Restore it any time from the Archived filter.')
            ->action(function (FlexibleSectionType $record): void {
                app(SetFlexibleSectionTypeArchivedAction::class)->execute($record, true);

                Notification::make()->success()->title('Section type archived')->send();
            });
    }

    public static function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (FlexibleSectionType $record): bool => $record->isArchived())
            ->action(function (FlexibleSectionType $record): void {
                app(SetFlexibleSectionTypeArchivedAction::class)->execute($record, false);

                Notification::make()->success()->title('Section type restored')->send();
            });
    }

    /**
     * Route deletion through the action layer; an in-use type refuses to
     * delete and surfaces the reason as a danger toast instead.
     */
    public static function delete(FlexibleSectionType $record): bool
    {
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
    }
}
