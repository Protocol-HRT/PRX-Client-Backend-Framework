<?php

namespace App\Filament\Resources\Catalog\Tags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                            ->hintIcon(Heroicon::InformationCircle, 'Tag label shown in admin lists and optionally on the frontend.')
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->hintIcon(Heroicon::InformationCircle, 'URL-friendly tag identifier. Auto-generated from the tag name.'),
                        TextInput::make('color')
                            ->maxLength(32)
                            ->placeholder('e.g. emerald, amber, rose')
                            ->hintIcon(Heroicon::InformationCircle, 'Optional Tailwind color name used to tint the tag pill on listing cards, e.g. emerald or rose.')
                            ->helperText('Optional Tailwind color name. Used to tint the tag pill on cards.'),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->hintIcon(Heroicon::InformationCircle, 'Controls display order in tag lists. Lower numbers appear first.'),
                        Toggle::make('is_visible')
                            ->default(true),
                    ]),
            ]);
    }
}
