<?php

namespace App\Filament\Resources\Commerce\Encounters\Schemas;

use App\Enums\EncounterStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EncounterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Encounter')
                    ->columns(3)
                    ->components([
                        TextInput::make('uuid')->disabled()->dehydrated(false),
                        Select::make('status')
                            ->options(EncounterStatus::class)
                            ->required()
                            ->native(false),
                        Toggle::make('is_sandbox')
                            ->label('Sandbox')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('prescribe_rx_encounter_id')
                            ->label('PRX encounter ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->copyable(),
                        TextInput::make('prescribe_rx_patient_id')
                            ->label('PRX patient ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->copyable(),
                        TextInput::make('prescribe_rx_encounter_type_id')
                            ->label('PRX encounter type ID')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                Section::make('Lifecycle')
                    ->columns(2)
                    ->components([
                        DateTimePicker::make('submitted_at'),
                        DateTimePicker::make('reviewed_at'),
                        DateTimePicker::make('completed_at'),
                        DateTimePicker::make('cancelled_at'),
                        TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('$'),
                    ]),
                Section::make('Metadata')
                    ->collapsed()
                    ->components([
                        KeyValue::make('metadata')
                            ->columnSpanFull()
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
