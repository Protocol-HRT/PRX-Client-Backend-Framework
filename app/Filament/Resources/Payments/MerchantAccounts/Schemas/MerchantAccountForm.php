<?php

namespace App\Filament\Resources\Payments\MerchantAccounts\Schemas;

use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MerchantAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(191),
                        Select::make('gateway_provider')
                            ->options(GatewayProvider::class)
                            ->required()
                            ->native(false)
                            ->live(),
                        Select::make('environment')
                            ->options(GatewayEnvironment::class)
                            ->required()
                            ->native(false)
                            ->default(GatewayEnvironment::Sandbox->value),
                        Toggle::make('is_active')->default(true),
                        Toggle::make('is_default')
                            ->helperText('Only one account should be set as default.'),
                    ]),

                Section::make('NMI credentials')
                    ->description('NMI Direct Post API credentials.')
                    ->visible(fn (Get $get): bool => $get('gateway_provider') === GatewayProvider::Nmi->value)
                    ->columns(2)
                    ->components([
                        TextInput::make('nmi_security_key')
                            ->label('Security Key')
                            ->helperText('Server-side key for Direct Post API. Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->columnSpanFull(),
                        TextInput::make('nmi_public_key')
                            ->label('Public Key (Collect.js)')
                            ->helperText('Client-side key for Collect.js tokenization. Safe to expose in browser.')
                            ->maxLength(512)
                            ->columnSpanFull(),
                    ]),

                Section::make('Authorize.Net credentials')
                    ->description('Authorize.Net API credentials.')
                    ->visible(fn (Get $get): bool => $get('gateway_provider') === GatewayProvider::AuthorizeNet->value)
                    ->columns(2)
                    ->components([
                        TextInput::make('authnet_api_login_id')
                            ->label('API Login ID')
                            ->helperText('Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512),
                        TextInput::make('authnet_transaction_key')
                            ->label('Transaction Key')
                            ->helperText('Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512),
                        TextInput::make('authnet_public_client_key')
                            ->label('Public Client Key (Accept.js)')
                            ->helperText('Used for Accept.js tokenization in the browser. Safe to expose.')
                            ->maxLength(512),
                        TextInput::make('authnet_signature_key')
                            ->label('Webhook Signature Key')
                            ->helperText('Used to verify HMAC signatures on inbound Authorize.Net webhooks. Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512),
                        Toggle::make('cim_enabled')
                            ->label('Enable CIM (Customer Information Manager)')
                            ->helperText('Enables customer vault for stored payment methods.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Capabilities')
                    ->columns(3)
                    ->components([
                        Toggle::make('allows_recurring_payments')->default(false),
                        Toggle::make('allows_rx_processing')->default(true),
                        Toggle::make('allows_card_present')->default(false),
                        Toggle::make('allows_card_not_present')->default(true),
                        Toggle::make('supports_public_checkout')->default(true),
                    ]),

                Section::make('Surcharge')
                    ->description('Optional surcharge applied to orders processed through this account.')
                    ->collapsed()
                    ->columns(3)
                    ->components([
                        TextInput::make('surcharge_rate')
                            ->label('Rate')
                            ->numeric()
                            ->suffix('%')
                            ->placeholder('e.g. 6.50')
                            ->helperText('Percentage of order total (e.g. 6.5 = 6.5%).'),
                        TextInput::make('surcharge_flat_per_txn')
                            ->label('Flat fee per transaction')
                            ->numeric()
                            ->prefix('$')
                            ->placeholder('0.00'),
                        Toggle::make('surcharge_passthrough')
                            ->label('Pass through to sales org')
                            ->helperText('When enabled, the surcharge is billed back to the org at settlement.'),
                    ]),

                Section::make('Volume & routing')
                    ->collapsed()
                    ->columns(2)
                    ->components([
                        TextInput::make('transaction_weight')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Higher weight = more traffic when multiple accounts are active.'),
                        TextInput::make('monthly_volume_limit')
                            ->numeric()
                            ->prefix('$')
                            ->placeholder('Unlimited'),
                        Toggle::make('auto_disable_at_limit')
                            ->label('Auto-disable when monthly limit reached'),
                    ]),
            ]);
    }
}
