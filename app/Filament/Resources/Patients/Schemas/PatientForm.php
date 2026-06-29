<?php

namespace App\Filament\Resources\Patients\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')->required()->maxLength(100),
                        TextInput::make('last_name')->required()->maxLength(100),
                        TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                        TextInput::make('phone')->tel()->maxLength(32)->nullable(),
                        DatePicker::make('date_of_birth')->nullable()->maxDate(now()->subYears(18)),
                    ]),

                Section::make('PRX Chart Link')
                    ->description('Links this patient to their clinical chart in Prescribe-Rx.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('prx_patient_chart_id')
                            ->label('Chart ID')
                            ->maxLength(64)
                            ->nullable()
                            ->helperText('UUID of the patient chart in PRX (the `id` field, not `patient_id`).'),
                        TextInput::make('prx_patient_id')
                            ->label('Patient ID')
                            ->maxLength(64)
                            ->nullable()
                            ->helperText('UUID of the PRX user account linked to the chart.'),
                        Toggle::make('prx_chart_collision_flagged')
                            ->label('Collision flagged')
                            ->helperText('Multiple PRX charts were found for this email. Requires manual review.')
                            ->inline(false),
                    ]),
            ]);
    }
}
