<?php

namespace App\Filament\Resources\Payments\MerchantAccounts\Tables;

use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MerchantAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gateway_provider')
                    ->label('Gateway')
                    ->badge()
                    ->color(fn (GatewayProvider $state): string => $state->color()),
                TextColumn::make('environment')
                    ->badge()
                    ->color(fn (GatewayEnvironment $state): string => $state->color()),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                TextColumn::make('monthly_volume_limit')
                    ->label('Monthly limit')
                    ->money('usd')
                    ->placeholder('Unlimited')
                    ->toggleable(),
                TextColumn::make('monthly_volume_used')
                    ->label('Volume used')
                    ->money('usd')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('is_default', 'desc')
            ->filters([
                SelectFilter::make('gateway_provider')->options(GatewayProvider::class),
                SelectFilter::make('environment')->options(GatewayEnvironment::class),
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
