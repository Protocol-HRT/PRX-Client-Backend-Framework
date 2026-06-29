<?php

namespace App\Filament\Resources\Commerce\FulfillmentCenters\Tables;

use App\Enums\FulfillmentCenterType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FulfillmentCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('system_type')
                    ->badge()
                    ->color(fn (FulfillmentCenterType $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('environment')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'production' ? 'success' : 'warning')
                    ->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                IconColumn::make('is_default')->boolean()->label('Default'),
                IconColumn::make('is_default_rx')->boolean()->label('Default Rx'),
                IconColumn::make('is_default_non_rx')->boolean()->label('Default non-Rx'),
                TextColumn::make('city')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('state')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('system_type')->options(FulfillmentCenterType::class),
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
