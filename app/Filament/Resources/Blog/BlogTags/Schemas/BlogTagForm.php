<?php

namespace App\Filament\Resources\Blog\BlogTags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogTagForm
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
                            ->alphaDash()
                            ->helperText('URL path. Lowercase letters, numbers, hyphens.'),
                        TextInput::make('color')
                            ->maxLength(32)
                            ->placeholder('#2563eb')
                            ->helperText('Hex color e.g. #2563eb'),
                        Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),
                    ]),
            ]);
    }
}
