<?php

namespace App\Filament\Resources\Catalog\Plans\Tables;

use App\Enums\BillingPeriod;
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
                TextColumn::make('provider_plan_id')
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
                    BulkAction::make('publish')
                        ->visible(fn (): bool => auth()->user()?->can('Update:Plan') ?? false)
                        ->label('Approve & Publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => CatalogStatus::Published]))
                        ->successNotificationTitle('Plans published'),
                    BulkAction::make('draft')
                        ->visible(fn (): bool => auth()->user()?->can('Update:Plan') ?? false)
                        ->label('Set to Draft')
                        ->icon('heroicon-o-pencil')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => CatalogStatus::Draft]))
                        ->successNotificationTitle('Plans set to Draft'),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
