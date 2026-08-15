<?php

namespace App\Filament\Resources\ApiClients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tokens_count')
                    ->label('Tokens')
                    ->counts('tokens')
                    ->sortable(),
                TextColumn::make('allowed_origins')
                    ->label('Origins')
                    ->getStateUsing(fn ($record) => $record->allowed_origins
                        ? implode(', ', $record->allowed_origins)
                        : '(any)')
                    ->placeholder('(any)')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('active')
                    ->label('Active only')
                    ->query(fn (Builder $query) => $query->where('is_active', true)),
                Filter::make('inactive')
                    ->label('Inactive only')
                    ->query(fn (Builder $query) => $query->where('is_active', false)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
