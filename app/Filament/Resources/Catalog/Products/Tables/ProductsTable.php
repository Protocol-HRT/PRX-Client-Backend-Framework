<?php

namespace App\Filament\Resources\Catalog\Products\Tables;

use App\Enums\CatalogStatus;
use Filament\Actions\BulkAction;
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
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
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
                    ->prefix('/products/')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CatalogStatus $state): string => $state->color())
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
                IconColumn::make('requires_lab')
                    ->boolean()
                    ->label('Lab'),
                TextColumn::make('provider_product_id')
                    ->label('PRX ID')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    BulkAction::make('publish')
                        ->label('Approve & Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => CatalogStatus::Published]))
                        ->successNotificationTitle('Products published'),
                    BulkAction::make('draft')
                        ->label('Set to Draft')
                        ->icon('heroicon-o-pencil')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => CatalogStatus::Draft]))
                        ->successNotificationTitle('Products set to Draft'),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
