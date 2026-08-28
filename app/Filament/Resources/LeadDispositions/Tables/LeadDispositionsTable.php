<?php

namespace App\Filament\Resources\LeadDispositions\Tables;

use App\Models\LeadDisposition;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadDispositionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->badge()
                    ->color(fn (LeadDisposition $record): string => $record->color),

                TextColumn::make('slug')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('description')
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),

                // The number that decides whether this row can be touched.
                // Surfacing it means an operator understands the lock before
                // they meet it, rather than after.
                TextColumn::make('leads_count')
                    ->label('Leads')
                    ->getStateUsing(fn (LeadDisposition $record): int => LeadDisposition::leadsUsing($record->slug))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                IconColumn::make('is_default')
                    ->label('Starting')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Selectable')
                    ->boolean(),

                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->tooltip('Written by application code. Cannot be deleted or re-slugged.'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // Hidden rather than shown-and-failing. The model still
                    // throws if something reaches past this, but an operator
                    // should not be offered a button that cannot work.
                    ->hidden(fn (LeadDisposition $record): bool => $record->is_system
                        || LeadDisposition::leadsUsing($record->slug) > 0),
            ]);
    }
}
