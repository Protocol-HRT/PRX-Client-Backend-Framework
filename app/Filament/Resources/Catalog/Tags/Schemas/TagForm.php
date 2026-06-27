<?php

namespace App\Filament\Resources\Catalog\Tags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tag')
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
                            ->required()
                            ->maxLength(255)
                            ->alphaDash(),
                        TextInput::make('color')
                            ->maxLength(32)
                            ->placeholder('e.g. emerald, amber, rose')
                            ->helperText('Optional Tailwind color name. Used to tint the tag pill on cards.'),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_visible')
                            ->default(true),
                    ]),
            ]);
    }
}
