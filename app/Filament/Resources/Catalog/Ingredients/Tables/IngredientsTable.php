<?php

namespace App\Filament\Resources\Catalog\Ingredients\Tables;

use App\Enums\Catalog\SexEligibility;
use App\Models\Catalog\Ingredient;
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

class IngredientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->copyable()->toggleable(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
                // Surfaced in the list, not buried in the form, because the
                // column defaults to "Anyone" for every row — an operator has
                // to be able to see at a glance which substances nobody has
                // classified yet. An unclassified male-only ingredient is
                // indistinguishable from a correctly unisex one otherwise.
                TextColumn::make('sex_eligibility')
                    ->label('Offered to')
                    ->badge()
                    ->formatStateUsing(fn (SexEligibility $state): string => $state->label())
                    ->color(fn (SexEligibility $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('age_range')
                    ->label('Age')
                    ->state(fn (Ingredient $record): ?string => $record->ageRangeLabel())
                    ->placeholder('Any')
                    ->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('provider_ingredient_id')
                    ->label('Provider ID')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('position', 'asc')
            ->filters([
                SelectFilter::make('sex_eligibility')
                    ->label('Offered to')
                    ->options(SexEligibility::options()),
                TernaryFilter::make('is_active'),
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
