<?php

namespace App\Filament\Resources\Kb\HealthGoals\RelationManagers;

use BackedEnum;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The override, not the main path.
 *
 * Most recommendations reach a product through its ingredients, which is what
 * the product actually contains. Pin one here when you want it on a goal
 * regardless of that — a bundle whose value is the combination, or a product
 * whose ingredient list does not tell the whole story.
 *
 * If you find yourself pinning most products by hand, the ingredient mappings
 * are the thing that is actually missing.
 */
class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Pinned products';

    protected static string|BackedEnum|null $icon = 'heroicon-o-cube';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::pivotFields());
    }

    /** @return array<int, Field> */
    private static function pivotFields(): array
    {
        return [
            TextInput::make('relevance_weight')
                ->label('Relevance')
                ->numeric()->minValue(0)->maxValue(100)->default(50)->required()
                ->hintIcon(Heroicon::InformationCircle, '0–100, ranked against the products reached through ingredients.'),
            Toggle::make('is_first_line')
                ->label('First-line')
                ->hintIcon(Heroicon::InformationCircle, 'Pin ahead of everything else for this goal.'),
            TextInput::make('relevance_note')
                ->label('Why it is relevant')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('health_goal_product.relevance_weight', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('pivot.relevance_weight')->label('Relevance')->badge()->sortable(),
                IconColumn::make('pivot.is_first_line')->label('First-line')->boolean(),
                TextColumn::make('pivot.relevance_note')->label('Note')->placeholder('—')->wrap()->toggleable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...self::pivotFields(),
                    ]),
            ])
            ->recordActions([EditAction::make(), DetachAction::make()])
            ->toolbarActions([BulkActionGroup::make([DetachBulkAction::make()])]);
    }
}
