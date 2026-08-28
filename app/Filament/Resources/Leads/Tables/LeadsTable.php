<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\CheckoutPath;
use App\Models\LeadDisposition;
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

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Captured')
                    ->since()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Disposition')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => LeadDisposition::labelFor($state))
                    ->color(fn (?string $state): string => LeadDisposition::colorFor($state))
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->getStateUsing(fn ($record) => trim("{$record->first_name} {$record->last_name}")),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone')->placeholder('—')->toggleable(),
                TextColumn::make('cart_subtotal')
                    ->label('Subtotal')
                    ->money('usd')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('checkout_path')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('sms_consent')
                    ->boolean()
                    ->label('SMS')
                    ->toggleable(),
                IconColumn::make('email_consent')
                    ->boolean()
                    ->label('Email')
                    ->toggleable(),
                TextColumn::make('utm_source')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prescribe_rx_encounter_id')
                    ->label('PRX encounter')
                    ->placeholder('—')
                    ->toggleable()
                    ->copyable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Disposition')->options(LeadDisposition::options()),
                SelectFilter::make('checkout_path')->options(CheckoutPath::class),
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
