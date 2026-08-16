<?php

namespace App\Filament\Resources\Commerce\FulfillmentCenters\Schemas;

use App\Enums\FulfillmentCenterType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FulfillmentCenterForm
{
    /** Handles both string (select) and enum (model hydration) state. */
    private static function isType(Get $get, FulfillmentCenterType $type): bool
    {
        $value = $get('system_type');

        if ($value instanceof FulfillmentCenterType) {
            return $value === $type;
        }

        return $value === $type->value;
    }

    private static function hasApiCredentials(Get $get): bool
    {
        $value = $get('system_type');
        $type = $value instanceof FulfillmentCenterType
            ? $value
            : FulfillmentCenterType::tryFrom((string) $value);

        return $type !== null && $type->requiresApiCredentials();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Fulfillment center')
                    ->vertical()
                    ->persistTabInQueryString('fulfillment-center-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Internal name for this fulfillment center, e.g. "Primary Pharmacy" or "ShipStation East".')
                                    ->columnSpanFull(),
                                Select::make('system_type')
                                    ->options(FulfillmentCenterType::class)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->hintIcon(Heroicon::InformationCircle, 'Fulfillment provider type. Determines which credential fields appear on the Configuration tab.')
                                    ->helperText('Select the fulfillment provider — credential fields appear on the Configuration tab.'),
                                Select::make('environment')
                                    ->options(['production' => 'Production', 'sandbox' => 'Sandbox'])
                                    ->required()
                                    ->native(false)
                                    ->hintIcon(Heroicon::InformationCircle, 'Use Sandbox for testing; Production for live order routing.')
                                    ->default('production'),
                                Toggle::make('is_active')->default(true),
                                Toggle::make('is_default')
                                    ->helperText('Default FC when no specific FC is set on the item.'),
                                Toggle::make('is_default_rx')
                                    ->label('Default for Rx items')
                                    ->helperText('Used when routing prescriptions to this FC.'),
                                Toggle::make('is_default_non_rx')
                                    ->label('Default for non-Rx items'),
                            ]),

                        // ── Configuration ─────────────────────────────
                        Tab::make('Configuration')
                            ->icon(Heroicon::PuzzlePiece)
                            ->visible(fn (Get $get): bool => ! blank($get('system_type')))
                            ->schema([

                                // ── No-API hint (Internal / Manual) ──────────────────
                                Section::make('Configuration')
                                    ->description('Internal and Manual FCs have no API credentials — fulfillment is tracked manually.')
                                    ->visible(fn (Get $get): bool => ! blank($get('system_type')) && ! self::hasApiCredentials($get))
                                    ->components([
                                        Placeholder::make('no_api_hint')
                                            ->label('')
                                            ->content('This fulfillment center type does not use API integration. Orders routed here are tracked and managed manually in the admin panel.'),
                                    ]),

                                // ── Prescribe-Rx ──────────────────────────────────────
                                Section::make('Prescribe-Rx configuration')
                                    ->description('Maps this fulfillment center to a fulfillment center record inside Prescribe-Rx. The FC ID is the internal UUID that Prescribe-Rx assigns to each fulfillment center — find it in the PRX admin under Settings → Fulfillment Centers. Orders routed through this local FC will reference that PRX FC ID when submitting to the unified intake API.')
                                    ->visible(fn (Get $get): bool => self::isType($get, FulfillmentCenterType::PrescribeRx))
                                    ->components([
                                        TextInput::make('prescribe_rx_fc_id')
                                            ->label('Prescribe-Rx Fulfillment Center ID')
                                            ->hintIcon(Heroicon::InformationCircle, 'UUID of this FC in the Prescribe-Rx admin (Settings → Fulfillment Centers). Not your API token.')
                                            ->helperText('UUID of this FC in the Prescribe-Rx admin (Settings → Fulfillment Centers → copy the ID column). This is NOT your API token or sales org ID.')
                                            ->placeholder('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                                            ->maxLength(64)
                                            ->columnSpanFull(),
                                    ]),

                                // ── ShipStation ───────────────────────────────────────
                                Section::make('ShipStation credentials')
                                    ->visible(fn (Get $get): bool => self::isType($get, FulfillmentCenterType::ShipStation))
                                    ->columns(2)
                                    ->components([
                                        TextInput::make('api_key')
                                            ->label('API Key')
                                            ->hintIcon(Heroicon::InformationCircle, 'ShipStation API Key from Account Settings → API Settings. Stored encrypted.')
                                            ->password()->revealable()->maxLength(512),
                                        TextInput::make('api_secret')
                                            ->label('API Secret')
                                            ->hintIcon(Heroicon::InformationCircle, 'ShipStation API Secret from Account Settings → API Settings. Stored encrypted.')
                                            ->password()->revealable()->maxLength(512),
                                        TextInput::make('shipstation_warehouse_id')
                                            ->label('Warehouse ID')
                                            ->hintIcon(Heroicon::InformationCircle, 'Numeric ShipStation warehouse ID used when creating shipments.')
                                            ->helperText('Numeric ShipStation warehouse ID used when creating shipments.')
                                            ->maxLength(64),
                                        TextInput::make('api_endpoint')
                                            ->label('API Endpoint Override')
                                            ->url()
                                            ->placeholder('https://ssapi.shipstation.com')
                                            ->hintIcon(Heroicon::InformationCircle, 'Override the default ShipStation API endpoint. Leave blank unless using a proxy.')
                                            ->helperText('Leave blank to use the default ShipStation endpoint.'),
                                    ]),

                                // ── 3PL / Warehouse ───────────────────────────────────
                                Section::make('3PL credentials')
                                    ->visible(fn (Get $get): bool => self::isType($get, FulfillmentCenterType::ThreePl))
                                    ->columns(2)
                                    ->components([
                                        TextInput::make('api_endpoint')
                                            ->label('API Endpoint')
                                            ->url()
                                            ->required()
                                            ->hintIcon(Heroicon::InformationCircle, 'Base URL for the 3PL provider API, e.g. https://api.your3pl.com/v1.')
                                            ->columnSpanFull(),
                                        TextInput::make('api_key')
                                            ->label('API Key / Client ID')
                                            ->hintIcon(Heroicon::InformationCircle, 'API key or OAuth client ID issued by the 3PL provider. Stored encrypted.')
                                            ->password()->revealable()->maxLength(512),
                                        TextInput::make('api_secret')
                                            ->label('API Secret / Client Secret')
                                            ->hintIcon(Heroicon::InformationCircle, 'API secret or OAuth client secret issued by the 3PL provider. Stored encrypted.')
                                            ->password()->revealable()->maxLength(512),
                                        TextInput::make('api_token')
                                            ->label('Bearer Token')
                                            ->hintIcon(Heroicon::InformationCircle, 'Static bearer token if the provider uses token auth instead of key+secret. Stored encrypted.')
                                            ->helperText('If the provider uses a static bearer token instead of key+secret.')
                                            ->password()->revealable()->maxLength(512)
                                            ->columnSpanFull(),
                                    ]),

                                // ── Custom API ────────────────────────────────────────
                                Section::make('Custom API credentials')
                                    ->visible(fn (Get $get): bool => self::isType($get, FulfillmentCenterType::CustomApi))
                                    ->columns(2)
                                    ->components([
                                        TextInput::make('api_endpoint')
                                            ->label('Endpoint URL')
                                            ->url()->required()
                                            ->hintIcon(Heroicon::InformationCircle, 'Base URL for the custom fulfillment API.')
                                            ->columnSpanFull(),
                                        TextInput::make('api_key')
                                            ->label('API Key / ID')
                                            ->hintIcon(Heroicon::InformationCircle, 'API key or client ID for authenticating with the custom API. Stored encrypted.')
                                            ->password()->revealable()->maxLength(512),
                                        TextInput::make('api_secret')
                                            ->label('API Secret')
                                            ->hintIcon(Heroicon::InformationCircle, 'API secret for authenticating with the custom API. Stored encrypted.')
                                            ->password()->revealable()->maxLength(512),
                                        TextInput::make('api_token')
                                            ->label('Bearer Token')
                                            ->hintIcon(Heroicon::InformationCircle, 'Static bearer token for the custom API if key+secret is not used. Stored encrypted.')
                                            ->password()->revealable()->maxLength(512)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ── Address & contact ─────────────────────────
                        Tab::make('Address & contact')
                            ->icon(Heroicon::MapPin)
                            ->columns(2)
                            ->schema([
                                TextInput::make('street_1')
                                    ->label('Street 1')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'First line of the fulfillment center street address.'),
                                TextInput::make('street_2')
                                    ->label('Street 2')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Suite, unit, or building number (optional).'),
                                TextInput::make('city')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'City of the fulfillment center.'),
                                TextInput::make('state')
                                    ->maxLength(8)
                                    ->label('State / Province')
                                    ->hintIcon(Heroicon::InformationCircle, '2-letter state or province code, e.g. TX or ON.'),
                                TextInput::make('postal_code')
                                    ->maxLength(16)
                                    ->hintIcon(Heroicon::InformationCircle, 'ZIP or postal code of the fulfillment center.'),
                                TextInput::make('country_code')
                                    ->label('Country code')
                                    ->maxLength(2)
                                    ->default('US')
                                    ->hintIcon(Heroicon::InformationCircle, 'ISO 3166-1 alpha-2 country code, e.g. US or CA.'),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(64)
                                    ->hintIcon(Heroicon::InformationCircle, 'Phone number for the fulfillment center contact.'),
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Email address for the fulfillment center contact.'),
                                TextInput::make('main_contact')
                                    ->label('Main contact name')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Name of the primary contact person at this fulfillment center.'),
                            ]),
                    ]),
            ]);
    }
}
