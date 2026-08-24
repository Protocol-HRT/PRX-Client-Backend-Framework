<?php

namespace App\Filament\Resources\Commerce\Encounters\Tables;

use App\Enums\EncounterStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EncountersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EncounterStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('lead.email')
                    ->label('Lead')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('prescribe_rx_encounter_id')
                    ->label('PRX encounter')
                    ->searchable()
                    ->copyable()
                    ->limit(16),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('usd')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_sandbox')
                    ->boolean()
                    ->label('Sandbox')
                    ->toggleable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(EncounterStatus::class),
                TernaryFilter::make('is_sandbox')->label('Sandbox'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
