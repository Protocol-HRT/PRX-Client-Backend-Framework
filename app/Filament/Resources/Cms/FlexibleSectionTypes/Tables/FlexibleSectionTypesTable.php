<?php

namespace App\Filament\Resources\Cms\FlexibleSectionTypes\Tables;

use App\Actions\Cms\DeleteFlexibleSectionTypeAction;
use App\Models\Cms\FlexibleSectionType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                    ->using(fn (FlexibleSectionType $record): bool => self::delete($record)),
            ]);
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
