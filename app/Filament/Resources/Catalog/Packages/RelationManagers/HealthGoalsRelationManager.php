<?php

namespace App\Filament\Resources\Catalog\Packages\RelationManagers;

use BackedEnum;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The badge OVERRIDE for a stack — leave it empty unless you need it.
 *
 * A stack normally shows the health-goal badges of the products inside it, so
 * tagging a product once updates every stack it appears in and nothing has to
 * be kept in step by hand.
 *
 * Attach goals here only when the derived set is wrong for how you sell this
 * stack — a stack marketed for one goal whose parts happen to treat five.
 * Anything attached REPLACES the derived badges rather than adding to them,
 * which is the point: the others have to disappear.
 *
 * This does not affect recommendations or the quiz. It is what the storefront
 * prints on a card.
 */
class HealthGoalsRelationManager extends RelationManager
{
    protected static string $relationship = 'healthGoals';

    protected static ?string $title = 'Badge override';

    protected static string|BackedEnum|null $icon = 'heroicon-o-tag';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Showing the badges of the products in this stack')
            ->emptyStateDescription('Attach a goal only to override that. Whatever you attach replaces the derived badges entirely.')
            ->defaultSort('health_goal_package.position')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('badge_color')
                    ->label('Badge colour')
                    ->placeholder('Site default')
                    ->badge(),
            ])
            ->headerActions([
                AttachAction::make()->preloadRecordSelect(),
            ])
            ->recordActions([DetachAction::make()])
            ->toolbarActions([BulkActionGroup::make([DetachBulkAction::make()])]);
    }
}
