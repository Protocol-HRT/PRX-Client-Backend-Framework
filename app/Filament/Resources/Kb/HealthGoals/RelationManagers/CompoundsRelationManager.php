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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The EDUCATION edge — it teaches, it does not sell.
 *
 * Attaching a compound here makes the goal appear on that compound's
 * knowledge-base page ("BPC-157 · supports recovery"). It recommends nothing
 * and puts nothing in a cart.
 *
 * It is separate from the ingredient edge because only 7 of 102 compounds map
 * to a catalog ingredient — deriving a monograph's goals from what the shop
 * sells would leave 95 pages showing none. A compound is worth writing about
 * whether or not this install stocks it.
 */
class CompoundsRelationManager extends RelationManager
{
    protected static string $relationship = 'compounds';

    protected static ?string $title = 'Explained by (knowledge base)';

    protected static string|BackedEnum|null $icon = 'heroicon-o-book-open';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::pivotFields());
    }

    /** @return array<int, Field> */
    private static function pivotFields(): array
    {
        return [
            Select::make('evidence_level')
                ->label('Evidence')
                ->options([
                    'strong' => 'Strong — human trials',
                    'moderate' => 'Moderate — limited human data',
                    'preliminary' => 'Preliminary — animal or mechanistic',
                    'anecdotal' => 'Anecdotal — reported use only',
                ])
                ->native(false),
            TextInput::make('relevance_note')
                ->label('What to say about it')
                ->maxLength(255)
                ->columnSpanFull()
                ->hintIcon(Heroicon::InformationCircle, 'Shown on the compound page under this goal. Editorial, not a sales line — this block is read by someone researching, not buying.'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                IconColumn::make('is_peptide')->label('Peptide')->boolean(),
                TextColumn::make('pivot.evidence_level')->label('Evidence')->badge()->placeholder('—'),
                TextColumn::make('pivot.relevance_note')->label('Note')->placeholder('—')->wrap()->toggleable(),
                IconColumn::make('is_published')->label('Live')->boolean(),
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
