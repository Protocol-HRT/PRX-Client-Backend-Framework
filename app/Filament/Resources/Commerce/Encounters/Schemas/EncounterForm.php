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
use Filament\Support\Icons\Heroicon;

class EncounterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Encounter')
                    ->columns(3)
                    ->components([
                        TextInput::make('uuid')
                            ->disabled()
                            ->dehydrated(false)
                            ->hintIcon(Heroicon::InformationCircle, 'Internal UUID assigned to this encounter. Read-only.'),
                        Select::make('status')
                            ->options(EncounterStatus::class)
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Current status of this encounter in the clinical workflow.')
                            ->native(false),
                        Toggle::make('is_sandbox')
                            ->label('Sandbox')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('prescribe_rx_encounter_id')
                            ->label('PRX encounter ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the linked encounter in Prescribe-Rx. Populated automatically on handoff.')
                            ->copyable(),
                        TextInput::make('prescribe_rx_patient_id')
                            ->label('PRX patient ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the patient record in Prescribe-Rx. Populated automatically on handoff.')
                            ->copyable(),
                        TextInput::make('prescribe_rx_encounter_type_id')
                            ->label('PRX encounter type ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the encounter type in Prescribe-Rx used for this intake. Read-only.'),
                    ]),
                Section::make('Lifecycle')
                    ->columns(2)
                    ->components([
                        DateTimePicker::make('submitted_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the encounter was submitted to the provider.'),
                        DateTimePicker::make('reviewed_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when a clinician reviewed this encounter.'),
                        DateTimePicker::make('completed_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the encounter was marked complete.'),
                        DateTimePicker::make('cancelled_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the encounter was cancelled.'),
                        TextInput::make('total_amount')
                            ->numeric()
                            ->prefix('$')
                            ->hintIcon(Heroicon::InformationCircle, 'Total transaction amount for this encounter as reported by the provider.'),
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
