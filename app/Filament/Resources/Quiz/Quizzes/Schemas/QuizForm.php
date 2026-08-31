<?php

namespace App\Filament\Resources\Quiz\Quizzes\Schemas;

use App\Cms\Support\CopyFields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class QuizForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Quiz')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Leave blank to generate from the name.')
                            ->hintIcon(Heroicon::InformationCircle, 'Used by the API and by any page pointing at a specific quiz. Changing it after launch breaks those links.'),
                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->hintIcon(Heroicon::InformationCircle, 'Internal note. Never shown to a visitor.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Toggle::make('is_default')
                            ->label('This is the quiz /quiz runs')
                            ->hintIcon(Heroicon::InformationCircle, 'Only one quiz can be the default. Turning this on turns it off everywhere else, so you never end up with two.'),
                    ]),

                // The words a visitor reads after answering. Authored here
                // rather than in the frontend because the two zero-match
                // states are an editorial decision, not a rendering one — see
                // the migration that added these columns.
                Section::make('Results page')
                    ->description('What the visitor sees after they finish. Leave a field blank to show nothing in that state.')
                    ->columns(1)
                    ->components([
                        CopyFields::inline('result_heading')
                            ->label('Heading')
                            ->hintIcon(Heroicon::InformationCircle, "Sits above the results. SHOWN IN EVERY STATE, including when there is nothing to recommend — so wording it as a promise ('Your recommended protocol') will read oddly above the two empty states below. Something neutral travels better. The visitor's first name is shown separately, so this need not greet them."),
                        CopyFields::prose('result_intro')
                            ->label('Intro — when there is something to recommend')
                            ->hintIcon(Heroicon::InformationCircle, 'Shown only when at least one goal matched. Skipped entirely when nothing did, so it can safely promise results.'),
                        CopyFields::prose('result_restricted_body')
                            ->label('When a goal has nothing suitable for this person')
                            ->hintIcon(Heroicon::InformationCircle, 'We stock this goal, but everything in it is excluded for their sex or age. Shown under that goal only. Say what to do next rather than why they were excluded.'),
                        CopyFields::prose('result_unmapped_body')
                            ->label('When a goal has not been built out yet')
                            ->hintIcon(Heroicon::InformationCircle, 'Nobody has mapped any ingredients to this goal, for anyone. Different from the field above, and the visitor must not be told they were ruled out.'),
                        CopyFields::prose('result_empty_body')
                            ->label('When there are no answers to build a plan from')
                            ->hintIcon(Heroicon::InformationCircle, 'They reached the page without completing the quiz, or every goal they picked has since been withdrawn.'),
                    ]),
            ]);
    }
}
