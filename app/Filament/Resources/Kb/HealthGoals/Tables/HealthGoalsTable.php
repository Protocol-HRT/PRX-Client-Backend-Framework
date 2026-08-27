<?php

namespace App\Filament\Resources\Kb\HealthGoals\Tables;

use App\Models\Kb\HealthGoal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HealthGoalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                // The class, not the glyph. Rendering the glyph would need the
                // Tabler webfont loaded in the ADMIN, and Vite does not rebase
                // the font URLs out of that package — the built theme points at
                // ./fonts/tabler-icons.woff2 and nothing copies it there. An
                // empty box is worse than the class string, so this shows what
                // is actually stored and the operator previews on tabler.io.
                TextColumn::make('icon')
                    ->label('Icon')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (HealthGoal $record): ?string => $record->prompt),

                // The three counts are the point of this table. A goal is only
                // as useful as what is mapped to it, and that is invisible
                // until you open the row otherwise.
                TextColumn::make('ingredients_count')
                    ->counts('ingredients')
                    ->label('Ingredients')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Pinned products')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('compounds_count')
                    ->counts('compounds')
                    ->label('Compounds')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('show_in_quiz')->label('In quiz')->boolean()->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
                TextColumn::make('parent.name')->label('Parent')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('show_in_quiz')->label('Offered in the quiz'),
                TernaryFilter::make('is_active')->label('Active'),

                // The working queue: a goal a visitor can pick that recommends
                // nothing. Compounds do not count — they teach, they do not
                // sell, so a goal with only compounds still dead-ends the quiz.
                Filter::make('unmapped')
                    ->label('Recommends nothing')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDoesntHave('ingredients')
                        ->whereDoesntHave('products')),

                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
