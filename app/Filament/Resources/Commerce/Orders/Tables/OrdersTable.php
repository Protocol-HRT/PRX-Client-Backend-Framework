<?php

namespace App\Filament\Resources\Commerce\Orders\Tables;

use App\Enums\OrderStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('placed_at')
                    ->label('Placed')
                    ->since()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('prescribe_rx_order_number')
                    ->label('Order #')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('encounter.lead.email')
                    ->label('Customer')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                TextColumn::make('shipments_count')
                    ->label('Shipments')
                    ->counts('shipments')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('usd')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('shipped_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class),
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
