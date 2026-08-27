<?php

namespace App\Filament\Resources\Kb\Compounds\Tables;

use App\Enums\Kb\RegulatoryStatus;
use App\Models\Kb\Compound;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompoundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Compound $record): ?string => $record->tagline),
                IconColumn::make('is_peptide')
                    ->label('Peptide')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('regulatory_status')
                    ->label('Regulatory')
                    ->badge()
                    ->formatStateUsing(fn (?RegulatoryStatus $state): string => $state?->label() ?? 'Not set')
                    ->color(fn (?RegulatoryStatus $state): string => $state?->color() ?? 'gray')
                    ->sortable(),
                TextColumn::make('reviewedBy.name')
                    ->label('Reviewed by')
                    ->placeholder('— awaiting review')
                    ->description(fn (Compound $record): ?string => $record->reviewed_at?->toFormattedDateString()),
                IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('ingredient.name')
                    ->label('Sold as')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_peptide')->label('Peptide'),
                TernaryFilter::make('is_published')->label('Published'),
                SelectFilter::make('regulatory_status')
                    ->label('Regulatory status')
                    ->options(RegulatoryStatus::options()),

                // The working queue. A regulatory status is the one thing that
                // blocks publication, so this filter is the list of what is
                // actually stopping the KB going live.
                Filter::make('needs_status')
                    ->label('Needs a regulatory status')
                    ->query(fn (Builder $query): Builder => $query->whereNull('regulatory_status')),

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
