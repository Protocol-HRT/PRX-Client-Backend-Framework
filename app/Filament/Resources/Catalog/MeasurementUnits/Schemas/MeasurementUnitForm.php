<?php

namespace App\Filament\Resources\Catalog\MeasurementUnits\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MeasurementUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Measurement unit')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(64)
                            ->live(onBlur: true)
                            ->hintIcon(Heroicon::InformationCircle, 'Full unit name, e.g. Milligram or Milliliter. Used in admin dropdowns for ingredient concentrations.'),
                        TextInput::make('abbreviation')
                            ->required()
                            ->maxLength(24)
                            ->hintIcon(Heroicon::InformationCircle, 'Symbol shown next to values, e.g. mg, mL or IU.'),
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
                        TextInput::make('provider_value')
                            ->label('Provider value')
                            ->numeric()
                            ->minValue(0)
                            ->hintIcon(Heroicon::InformationCircle, 'Numeric enum value the provider uses for this measurement unit.'),
                    ]),
            ]);
    }
}
