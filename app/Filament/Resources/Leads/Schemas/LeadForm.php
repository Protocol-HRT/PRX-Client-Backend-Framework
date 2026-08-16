<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\CheckoutPath;
use App\Enums\LeadStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Lead')
                    ->vertical()
                    ->persistTabInQueryString('lead-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(3)
                            ->schema([
                                TextInput::make('uuid')
                                    ->label('UUID')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->hintIcon(Heroicon::InformationCircle, 'Internal UUID for this lead. Read-only.'),
                                Select::make('status')
                                    ->options(LeadStatus::class)
                                    ->required()
                                    ->native(false)
                                    ->hintIcon(Heroicon::InformationCircle, 'Current status of this lead in the workflow.'),
                                Select::make('checkout_path')
                                    ->label('Checkout path')
                                    ->options(CheckoutPath::class)
                                    ->native(false)
                                    ->hintIcon(Heroicon::InformationCircle, 'Which checkout flow this lead is routed through (e.g. PRX embed or local checkout).'),

                                Section::make('Contact')
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->components([
                                        TextInput::make('first_name')
                                            ->required()
                                            ->hintIcon(Heroicon::InformationCircle, 'Patient first name as entered at intake.'),
                                        TextInput::make('last_name')
                                            ->required()
                                            ->hintIcon(Heroicon::InformationCircle, 'Patient last name as entered at intake.'),
                                        TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->hintIcon(Heroicon::InformationCircle, 'Lead or patient email address.'),
                                        TextInput::make('phone')
                                            ->tel()
                                            ->hintIcon(Heroicon::InformationCircle, 'Patient contact phone number.'),
                                        DatePicker::make('date_of_birth')
                                            ->hintIcon(Heroicon::InformationCircle, 'Patient date of birth. Required for certain encounter types.'),
                                    ]),
                            ]),

                        // ── Address & consents ────────────────────────
                        Tab::make('Address & consents')
                            ->icon(Heroicon::MapPin)
                            ->columns(3)
                            ->schema([
                                TextInput::make('address_line1')
                                    ->columnSpan(2)
                                    ->hintIcon(Heroicon::InformationCircle, 'Street address line 1.'),
                                TextInput::make('address_line2')
                                    ->columnSpan(1)
                                    ->hintIcon(Heroicon::InformationCircle, 'Apt, suite, or unit (optional).'),
                                TextInput::make('city')
                                    ->hintIcon(Heroicon::InformationCircle, 'City.'),
                                TextInput::make('state')
                                    ->maxLength(8)
                                    ->hintIcon(Heroicon::InformationCircle, '2-letter state code, e.g. TX.'),
                                TextInput::make('postal_code')
                                    ->maxLength(16)
                                    ->hintIcon(Heroicon::InformationCircle, 'ZIP or postal code.'),
                                TextInput::make('country')
                                    ->maxLength(2)
                                    ->default('US')
                                    ->hintIcon(Heroicon::InformationCircle, 'ISO 3166-1 alpha-2 country code, e.g. US.'),

                                Section::make('Consents')
                                    ->columnSpanFull()
                                    ->columns(3)
                                    ->components([
                                        Toggle::make('sms_consent')->label('SMS consent'),
                                        Toggle::make('email_consent')->label('Email consent'),
                                        DateTimePicker::make('consent_given_at')
                                            ->label('Consent given at')
                                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the patient gave marketing consent.'),
                                    ]),
                            ]),

                        // ── Commerce ──────────────────────────────────
                        Tab::make('Commerce')
                            ->icon(Heroicon::ShoppingCart)
                            ->columns(2)
                            ->schema([
                                Section::make('Cart snapshot')
                                    ->description('Locked at the moment of lead capture.')
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->components([
                                        TextInput::make('cart_subtotal')
                                            ->numeric()
                                            ->prefix('$')
                                            ->hintIcon(Heroicon::InformationCircle, 'Cart subtotal in dollars at the moment of lead capture.'),
                                        TextInput::make('cart_items_count')
                                            ->label('Item count')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(fn ($record) => is_array($record?->cart_items) ? count($record->cart_items) : 0),
                                    ]),

                                Section::make('prescribe-rx handoff')
                                    ->columnSpanFull()
                                    ->columns(2)
                                    ->components([
                                        TextInput::make('prescribe_rx_encounter_id')
                                            ->label('PRX encounter ID')
                                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the linked encounter in Prescribe-Rx. Populated on handoff.'),
                                        TextInput::make('prescribe_rx_patient_id')
                                            ->label('PRX patient ID')
                                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the patient record in Prescribe-Rx. Populated on handoff.'),
                                        DateTimePicker::make('handed_off_at')
                                            ->label('Handed off at')
                                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when this lead was handed off to Prescribe-Rx.'),
                                        DateTimePicker::make('completed_at')
                                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the full encounter was marked complete.'),
                                    ]),
                            ]),

                        // ── Attribution ───────────────────────────────
                        Tab::make('Attribution')
                            ->icon(Heroicon::ChartBar)
                            ->columns(3)
                            ->schema([
                                TextInput::make('utm_source')
                                    ->hintIcon(Heroicon::InformationCircle, 'UTM source from the URL at signup, e.g. google.'),
                                TextInput::make('utm_medium')
                                    ->hintIcon(Heroicon::InformationCircle, 'UTM medium, e.g. cpc or email.'),
                                TextInput::make('utm_campaign')
                                    ->hintIcon(Heroicon::InformationCircle, 'UTM campaign name.'),
                                TextInput::make('utm_term')
                                    ->hintIcon(Heroicon::InformationCircle, 'UTM term (paid keyword).'),
                                TextInput::make('utm_content')
                                    ->hintIcon(Heroicon::InformationCircle, 'UTM content for A/B differentiation.'),
                                TextInput::make('referrer')->columnSpan(3)
                                    ->hintIcon(Heroicon::InformationCircle, 'HTTP referrer URL when the lead was captured.'),
                                TextInput::make('landing_url')->columnSpan(3)
                                    ->hintIcon(Heroicon::InformationCircle, 'Landing page URL the visitor was on at lead capture.'),
                                TextInput::make('ip_address')
                                    ->hintIcon(Heroicon::InformationCircle, 'IP address recorded at consent time for compliance.'),
                                TextInput::make('user_agent')->columnSpan(2)
                                    ->hintIcon(Heroicon::InformationCircle, 'Browser user-agent string at the time of lead capture.'),
                            ]),

                        // ── Notes ─────────────────────────────────────
                        Tab::make('Notes')
                            ->icon(Heroicon::PencilSquare)
                            ->schema([
                                Textarea::make('notes')
                                    ->rows(4)
                                    ->hintIcon(Heroicon::InformationCircle, 'Internal operator notes. Not visible to the patient.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
