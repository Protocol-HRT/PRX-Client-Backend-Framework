<?php

namespace App\Filament\Resources\Integrations\Tables;

use App\Integrations\IntegrationRegistry;
use App\Models\Integrations\IntegrationInstance;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The at-a-glance answer to "where does our data go, and who may see health
 * information?" — which is the question this table exists to answer without
 * anyone opening a record.
 */
class IntegrationInstancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),

                TextColumn::make('provider')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => app(IntegrationRegistry::class)
                        ->providerOptions()[$state] ?? $state.' (not installed)'),

                TextColumn::make('capabilities')
                    ->label('Used for')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state))
                    ->placeholder('nothing enabled'),

                IconColumn::make('is_active')->label('On')->boolean(),

                IconColumn::make('phi_permitted')
                    ->label('Health data')
                    ->boolean()
                    // Green for permitted would read as "good". Neither state is
                    // good or bad — one is a declared permission and the other is
                    // its absence — so the emphasis goes on the one with
                    // consequences.
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (IntegrationInstance $record): string => $record->phi_permitted
                        ? 'Someone has attested that this provider may receive health data.'
                        : 'Health fields are blocked or redacted for this destination.'),

                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }
}
