<?php

namespace App\Filament\Resources\Catalog\Packages\Tables;

use App\Enums\CatalogStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->prefix('/packages/')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CatalogStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('plans_count')
                    ->label('Plans')
                    ->counts('plans')
                    ->sortable(),
                TextColumn::make('retail_price')
                    ->label('Retail')
                    ->money('usd')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('sale_price')
                    ->label('Sale')
                    ->money('usd')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
                TextColumn::make('prescribe_rx_package_number')
                    ->label('PRX #')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('position', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options(CatalogStatus::class),
                TernaryFilter::make('is_featured'),
                TernaryFilter::make('requires_lab')->label('Requires lab'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
