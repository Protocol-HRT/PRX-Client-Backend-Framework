<?php

namespace App\Filament\Resources\Catalog\Ingredients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class IngredientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ingredient')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->hintIcon(Heroicon::InformationCircle, 'Active ingredient name, e.g. Sermorelin. Referenced by product concentration rows.')
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->alphaDash()
                            ->helperText('Leave blank to auto-generate from the name.')
                            ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier used in API responses and frontend routes.'),
                        TextInput::make('short_name')
                            ->maxLength(64)
                            ->hintIcon(Heroicon::InformationCircle, 'Compact label used where space is tight, e.g. ingredient chips on product cards.'),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('position')
                            ->numeric()
                            ->default(0)
                            ->hintIcon(Heroicon::InformationCircle, 'Controls display order in lists. Lower numbers appear first.'),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                Section::make('Provider mapping')
                    ->description('Optional mapping to the fulfillment provider (e.g. PrescribeRx) so synced products reuse this row. Leave blank for non-provider vocabulary.')
                    ->columns(2)
                    ->components([
                        TextInput::make('provider_ingredient_id')
                            ->label('Provider ingredient ID')
                            ->maxLength(36)
                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the matching ingredient on the provider side.'),
                    ]),
            ]);
    }
}
