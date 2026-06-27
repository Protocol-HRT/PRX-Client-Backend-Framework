<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\CheckoutPath;
use App\Enums\LeadStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lead')
                    ->columns(3)
                    ->components([
                        TextInput::make('uuid')
                            ->label('UUID')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('status')
                            ->options(LeadStatus::class)
                            ->required()
                            ->native(false),
                        Select::make('checkout_path')
                            ->label('Checkout path')
                            ->options(CheckoutPath::class)
                            ->native(false),
                    ]),
                Section::make('Contact')
                    ->columns(2)
                    ->components([
                        TextInput::make('first_name')->required(),
                        TextInput::make('last_name')->required(),
                        TextInput::make('email')->email()->required(),
                        TextInput::make('phone')->tel(),
                        DatePicker::make('date_of_birth'),
                    ]),
                Section::make('Address')
                    ->columns(3)
                    ->components([
                        TextInput::make('address_line1')->columnSpan(2),
                        TextInput::make('address_line2')->columnSpan(1),
                        TextInput::make('city'),
                        TextInput::make('state')->maxLength(8),
                        TextInput::make('postal_code')->maxLength(16),
                        TextInput::make('country')->maxLength(2)->default('US'),
                    ]),
                Section::make('Consents')
                    ->columns(3)
                    ->components([
                        Toggle::make('sms_consent')->label('SMS consent'),
                        Toggle::make('email_consent')->label('Email consent'),
                        DateTimePicker::make('consent_given_at')->label('Consent given at'),
                    ]),
                Section::make('Cart snapshot')
                    ->description('Locked at the moment of lead capture.')
                    ->columns(2)
                    ->components([
                        TextInput::make('cart_subtotal')
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('cart_items_count')
                            ->label('Item count')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => is_array($record?->cart_items) ? count($record->cart_items) : 0),
                    ]),
                Section::make('prescribe-rx handoff')
                    ->columns(2)
                    ->components([
                        TextInput::make('prescribe_rx_encounter_id')->label('PRX encounter ID'),
                        TextInput::make('prescribe_rx_patient_id')->label('PRX patient ID'),
                        DateTimePicker::make('handed_off_at')->label('Handed off at'),
                        DateTimePicker::make('completed_at'),
                    ]),
                Section::make('Marketing attribution')
                    ->collapsed()
                    ->columns(3)
                    ->components([
                        TextInput::make('utm_source'),
                        TextInput::make('utm_medium'),
                        TextInput::make('utm_campaign'),
                        TextInput::make('utm_term'),
                        TextInput::make('utm_content'),
                        TextInput::make('referrer')->columnSpan(3),
                        TextInput::make('landing_url')->columnSpan(3),
                        TextInput::make('ip_address'),
                        TextInput::make('user_agent')->columnSpan(2),
                    ]),
                Section::make('Notes')
                    ->components([
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
