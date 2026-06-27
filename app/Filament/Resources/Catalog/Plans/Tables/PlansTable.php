<?php

namespace App\Filament\Resources\Catalog\Plans\Tables;

use App\Enums\BillingPeriod;
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

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package.name')
                    ->label('Package')
                    ->placeholder('Standalone')
                    ->toggleable(),
                TextColumn::make('billing_period')
                    ->badge()
                    ->formatStateUsing(fn (BillingPeriod $state): string => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CatalogStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('retail_price')
                    ->label('Retail')
                    ->money('usd')
                    ->placeholder('—'),
                TextColumn::make('sale_price')
                    ->label('Sale')
                    ->money('usd')
                    ->placeholder('—'),
                IconColumn::make('is_featured')->boolean()->label('Featured'),
                TextColumn::make('prescribe_rx_plan_number')
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
                SelectFilter::make('status')->options(CatalogStatus::class),
                SelectFilter::make('billing_period')->options(BillingPeriod::class),
                TernaryFilter::make('is_featured'),
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
