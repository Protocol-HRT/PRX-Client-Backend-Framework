<?php

namespace App\Filament\Resources\EmailSubscribers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EmailSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                IconColumn::make('email_consent')
                    ->boolean(),
                IconColumn::make('sms_consent')
                    ->boolean(),
                TextColumn::make('consent_given_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('utm_source')
                    ->searchable(),
                TextColumn::make('utm_medium')
                    ->searchable(),
                TextColumn::make('utm_campaign')
                    ->searchable(),
                TextColumn::make('utm_term')
                    ->searchable(),
                TextColumn::make('utm_content')
                    ->searchable(),
                TextColumn::make('referrer')
                    ->searchable(),
                TextColumn::make('landing_url')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->searchable(),
                TextColumn::make('user_agent')
                    ->searchable(),
                TextColumn::make('subscribed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('unsubscribed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
