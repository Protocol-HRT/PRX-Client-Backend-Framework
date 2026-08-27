<?php

namespace App\Filament\Resources\Kb\HealthGoals\RelationManagers;

use BackedEnum;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * THE edge the quiz recommends through.
 *
 * Mapping a goal to an ingredient is what turns "I want to sleep better" into
 * a product, because an ingredient is what a product actually contains. Every
 * product holding a mapped ingredient becomes a candidate, and every package
 * holding one of those products follows — which is why neither is mapped here.
 */
class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

    protected static ?string $title = 'Recommends (via ingredients)';

    protected static string|BackedEnum|null $icon = 'heroicon-o-beaker';

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
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->default(50)
                ->required()
                ->hintIcon(Heroicon::InformationCircle, '0–100. Ranks which ingredients surface first when more match a goal than a plan can show.'),
            Select::make('evidence_level')
                ->label('Evidence')
                ->options([
                    'strong' => 'Strong — human trials',
                    'moderate' => 'Moderate — limited human data',
                    'preliminary' => 'Preliminary — animal or mechanistic',
                    'anecdotal' => 'Anecdotal — reported use only',
                ])
                ->native(false)
                ->hintIcon(Heroicon::InformationCircle, 'How well supported this pairing is. Separate from relevance on purpose: strong evidence for a mild effect is not the same as weak evidence for a large one, and one number cannot say both.'),
            Toggle::make('is_first_line')
                ->label('First-line')
                ->hintIcon(Heroicon::InformationCircle, 'Pin this as the default answer for the goal, ahead of anything ranked above it.'),
            TextInput::make('relevance_note')
                ->label('Why it is relevant')
                ->maxLength(255)
                ->columnSpanFull()
                ->hintIcon(Heroicon::InformationCircle, 'Shown to the visitor on the plan — "Supports fat loss while holding lean muscle".'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('health_goal_ingredient.relevance_weight', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('pivot.relevance_weight')->label('Relevance')->badge()->sortable(),
                TextColumn::make('pivot.evidence_level')->label('Evidence')->badge()->placeholder('—'),
                IconColumn::make('pivot.is_first_line')->label('First-line')->boolean(),
                TextColumn::make('pivot.relevance_note')->label('Note')->placeholder('—')->wrap()->toggleable(),
                // What this mapping actually reaches. A weight against an
                // ingredient nothing sells recommends nothing.
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('In products')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning'),
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
