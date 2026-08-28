<?php

namespace App\Filament\Resources\Quiz\Quizzes\RelationManagers;

use App\Enums\Privacy\DataClassification;
use App\Enums\Quiz\QuizQuestionKind;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * One screen of the quiz, with its questions and their options edited in
 * place.
 *
 * NESTED REPEATERS RATHER THAN A THIRD RESOURCE, deliberately. A step is
 * meaningless without its questions — nobody edits "step 4" and then navigates
 * somewhere else to see what it asks — and three levels of resource would make
 * reordering a question a two-page job. The cost is a tall modal, which is the
 * right trade for a form an operator reads top to bottom in the order the
 * visitor meets it.
 *
 * Every repeater is `defaultItems(0)`. A repeater that opens with one blank
 * row makes required inner fields fail validation on a tab the operator never
 * opened, and Filament reports the error against a field they cannot see — the
 * failure that once made products unsaveable here.
 */
class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Steps';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Step')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->hintIcon(Heroicon::InformationCircle, 'Internal label, and the caption in the progress bar.'),
                    TextInput::make('slug')
                        ->required()
                        ->alphaDash()
                        ->maxLength(255)
                        ->hintIcon(Heroicon::InformationCircle, 'Identifies the step. Conditions on other steps can reference it.'),
                    TextInput::make('heading')
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->hintIcon(Heroicon::InformationCircle, 'The question at the top of the screen, in the visitor\'s words.'),
                    Textarea::make('description')
                        ->rows(2)
                        ->columnSpanFull()
                        ->helperText('The reassurance under it — "No wrong answer. Most people start from zero." This line does real work in a funnel; it is not decoration.'),
                    TextInput::make('position')->numeric()->default(0),
                    Toggle::make('is_active')->label('Active')->default(true),
                    self::conditions()
                        ->label('Show this step only when')
                        ->helperText('Leave empty to always show it. A whole step can be conditional — hiding its questions one by one would leave an empty screen with a Continue button.'),
                ]),

            Section::make('Questions')
                ->components([
                    Repeater::make('questions')
                        ->relationship()
                        ->label('')
                        ->defaultItems(0)
                        ->addActionLabel('Add question')
                        ->collapsed()
                        ->orderColumn('position')
                        ->itemLabel(fn (array $state): ?string => $state['prompt'] ?? null)
                        ->columns(2)
                        ->schema([
                            Select::make('kind')
                                ->options(QuizQuestionKind::options())
                                ->default(QuizQuestionKind::SingleSelect->value)
                                ->required()
                                ->native(false)
                                ->live()
                                ->helperText(fn (?string $state): string => QuizQuestionKind::tryFrom($state ?? '')?->description()
                                    ?? 'What this question asks for.'),
                            TextInput::make('slug')
                                ->required()
                                ->alphaDash()
                                ->maxLength(255)
                                ->hintIcon(Heroicon::InformationCircle, 'The key the answer is filed under, for the life of the quiz. Conditions and the report both reference it — renaming one orphans every answer already given. Add a new question instead.'),
                            TextInput::make('prompt')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Textarea::make('help')
                                ->rows(2)
                                ->columnSpanFull()
                                ->helperText('Optional line under the question.'),
                            Toggle::make('is_required')->label('Required')->default(true),
                            Toggle::make('is_active')->label('Active')->default(true),

                            // THE DOWNGRADE CONTROL, and it has to exist here or
                            // the protective default becomes a wall. Every
                            // authored question is treated as health data until
                            // somebody says otherwise, which is right for a
                            // health quiz and wrong for "how did you hear about
                            // us?" — and without this control the only way to
                            // say otherwise would be a manual database write.
                            Select::make('data_class')
                                ->label('Sensitivity')
                                ->native(false)
                                ->placeholder(fn (?string $state, $get): string => 'Automatic — '
                                    .(QuizQuestionKind::tryFrom($get('kind') ?? '')?->defaultDataClassification()->label() ?? 'Health (PHI)'))
                                ->options(DataClassification::options())
                                ->helperText('Controls where this answer may be sent. Leave on Automatic unless you know this question is not clinical — answers marked as health data are blocked from integrations you have not approved for it.'),

                            self::conditions()
                                ->label('Ask this only when')
                                ->helperText('Leave empty to always ask it. Use "contains" against a multi-answer question such as health goals.'),

                            Repeater::make('options')
                                ->relationship()
                                ->label('Answers')
                                ->defaultItems(0)
                                ->addActionLabel('Add answer')
                                ->collapsed()
                                ->orderColumn('position')
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                ->columnSpanFull()
                                ->columns(2)
                                // Hidden for the reserved kinds. An authored
                                // option there would be silently ignored at
                                // serve time, and a control that discards what
                                // an operator typed is worse than one that is
                                // not offered.
                                ->visible(fn (callable $get): bool => (bool) QuizQuestionKind::tryFrom($get('kind') ?? '')?->usesAuthoredOptions())
                                ->schema([
                                    TextInput::make('label')->required()->maxLength(255),
                                    TextInput::make('value')
                                        ->required()
                                        ->alphaDash()
                                        ->maxLength(255)
                                        ->hintIcon(Heroicon::InformationCircle, 'Stored with the answer. Conditions reference it, so treat it as permanent.'),
                                    TextInput::make('description')
                                        ->maxLength(255)
                                        ->columnSpanFull()
                                        ->helperText('Optional second line on the card.'),
                                    TextInput::make('icon')
                                        ->maxLength(64)
                                        ->placeholder('ti ti-flame')
                                        ->helperText('A Tabler icon class, same as health goals use.'),
                                    Select::make('price_source')
                                        ->label('Show a price range from')
                                        ->options([
                                            'products' => 'All products',
                                            'packages' => 'All packages',
                                            'packages:protocol' => 'Packages tiered "protocol"',
                                            'packages:stack' => 'Packages tiered "stack"',
                                        ])
                                        ->native(false)
                                        ->placeholder('No price')
                                        ->helperText('The range is calculated from live plan prices every time the quiz is served. You never type a price here — one typed next to a buying decision goes stale without anyone noticing.'),
                                    Toggle::make('is_exclusive')
                                        ->label('Clears the other answers')
                                        ->hintIcon(Heroicon::InformationCircle, 'For a "None of these" option. Picking it clears the rest, and picking anything else clears it — otherwise a visitor can answer "none of these, and also high blood pressure".'),
                                ]),
                        ]),
                ]),
        ]);
    }

    /** The CMS's condition builder, verbatim plus the two membership operators. */
    private static function conditions(): Repeater
    {
        return Repeater::make('visible_when')
            ->defaultItems(0)
            ->addActionLabel('Add condition')
            ->columnSpanFull()
            ->columns(3)
            ->schema([
                TextInput::make('field')
                    ->required()
                    ->helperText('The slug of an earlier question.'),
                Select::make('operator')
                    ->options([
                        'equals' => 'Equals',
                        'not_equals' => 'Does not equal',
                        'contains' => 'Includes',
                        'not_contains' => 'Does not include',
                    ])
                    ->default('equals')
                    ->native(false),
                TextInput::make('value')->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('heading')->limit(48)->placeholder('—'),
                TextColumn::make('questions_count')->counts('questions')->label('Questions'),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
