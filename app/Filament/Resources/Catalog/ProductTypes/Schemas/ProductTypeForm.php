<?php

namespace App\Filament\Resources\Catalog\ProductTypes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ProductTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product type')
                    ->columns(2)
                    ->components([
                        Select::make('product_class_id')
                            ->label('Product class')
                            ->relationship('productClass', 'name')
                            ->nullable()
                            ->preload()
                            ->searchable()
                            ->hintIcon(Heroicon::InformationCircle, 'Optional parent class this type belongs to, e.g. Injectable under Peptides.'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->hintIcon(Heroicon::InformationCircle, 'Type label shown in admin filters and frontend product classification.')
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
                            ->hintIcon(Heroicon::InformationCircle, 'Compact label used where space is tight, e.g. badges and dropdowns.'),
                        Textarea::make('description')
                            ->rows(3)
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
                        TextInput::make('provider_product_type_id')
                            ->label('Provider product type ID')
                            ->maxLength(36)
                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the matching product type on the provider side.'),
                    ]),
            ]);
    }
}
