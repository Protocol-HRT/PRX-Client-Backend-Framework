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
            ->modifyQueryUsing(fn ($query) => $query->with('regionItems'))
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
                TextColumn::make('mounted_in')
                    ->label('Mounted in')
                    ->state(fn (Menu $record): array => $record->regionItems
                        ->map(fn ($item): string => $item->region->label())
                        ->unique()
                        ->values()
                        ->whenEmpty(fn ($c) => collect(['Not mounted']))
                        ->all())
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Not mounted' ? 'warning' : 'gray')
                    ->tooltip('Regions in Site Layout that include this menu. An unmounted menu is still available at /api/v1/menus/{slug}, but nothing renders it automatically.'),
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
