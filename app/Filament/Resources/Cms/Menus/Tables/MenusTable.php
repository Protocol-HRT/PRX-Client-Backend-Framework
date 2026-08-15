<?php

namespace App\Filament\Resources\Cms\Menus\Tables;

use App\Actions\Cms\DeleteMenuAction;
use App\Models\Cms\Menu;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenusTable
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
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Deleting a menu also deletes all of its items. This cannot be undone.')
                    ->using(function (Menu $record): bool {
                        app(DeleteMenuAction::class)->execute($record);

                        return true;
                    }),
            ]);
    }
}
