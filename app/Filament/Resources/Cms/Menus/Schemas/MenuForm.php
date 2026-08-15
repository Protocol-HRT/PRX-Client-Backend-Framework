<?php

namespace App\Filament\Resources\Cms\Menus\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Menu')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(64)
                            ->alphaDash()
                            ->disabledOn('edit')
                            ->helperText('Stable identifier the frontend requests this menu by (e.g. "main-nav"). Cannot be changed after creation.'),
                        Textarea::make('description')
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Internal note about where this menu is used.'),
                    ]),
            ]);
    }
}
