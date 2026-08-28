<?php

namespace App\Filament\Resources\Quiz\Quizzes\Schemas;

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
            ]);
    }
}
