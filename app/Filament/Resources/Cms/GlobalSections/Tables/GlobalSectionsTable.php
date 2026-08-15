<?php

namespace App\Filament\Resources\Cms\GlobalSections\Tables;

use App\Actions\Cms\DeleteGlobalSectionAction;
use App\Models\Cms\GlobalSection;
use App\Services\Cms\SectionRegistry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use RuntimeException;

class GlobalSectionsTable
{
    public static function configure(Table $table): Table
    {
        $registry = app(SectionRegistry::class);

        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->badge()
                    ->copyable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $registry->labelFor($state)),
                TextColumn::make('usage')
                    ->label('In use')
                    ->state(fn (GlobalSection $record): int => $record->usageCount()),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('enabled'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(fn (GlobalSection $record): bool => self::delete($record)),
            ]);
    }

    /**
     * Route deletion through the action layer; an in-use block refuses to
     * delete and surfaces the reason as a danger toast instead.
     */
    public static function delete(GlobalSection $record): bool
    {
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
    }
}
