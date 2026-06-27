<?php

namespace App\Filament\Resources\Payments\MerchantAccounts\Schemas;

use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MerchantAccountForm
{
    /** Handles both string state (user picking from select) and enum state (Filament hydrating from model). */
    private static function isGateway(Get $get, GatewayProvider $provider): bool
    {
        $value = $get('gateway_provider');

        if ($value instanceof GatewayProvider) {
            return $value === $provider;
        }

        return $value === $provider->value;
    }

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
                            ->live()
                            ->helperText('After selecting a gateway, its credential fields will appear below.'),
                        Select::make('environment')
                            ->options(GatewayEnvironment::class)
                            ->required()
                            ->native(false)
                            ->default(GatewayEnvironment::Sandbox->value),
                        Toggle::make('is_active')->default(true),
                        Toggle::make('is_default')
                            ->helperText('Only one account should be set as default.'),
                    ]),

                // Hint shown when no gateway has been selected yet
                Section::make('Credentials')
                    ->description('Select a gateway provider above — the matching credential fields will appear here.')
                    ->visible(fn (Get $get): bool => blank($get('gateway_provider')))
                    ->components([
                        Placeholder::make('no_gateway_hint')
                            ->label('')
                            ->content('No gateway selected. Choose NMI, Authorize.Net, Stripe, or Square from the Account section above.'),
                    ]),

                // ── NMI credentials ───────────────────────────────
                Section::make('NMI credentials')
                    ->description('NMI Direct Post API credentials.')
                    ->visible(fn (Get $get): bool => self::isGateway($get, GatewayProvider::Nmi))
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
                            ->helperText('Client-side key for Collect.js tokenization. Safe to expose in the browser.')
                            ->maxLength(512)
                            ->columnSpanFull(),
                    ]),

                // ── Authorize.Net credentials ─────────────────────
                Section::make('Authorize.Net credentials')
                    ->description('Authorize.Net API credentials.')
                    ->visible(fn (Get $get): bool => self::isGateway($get, GatewayProvider::AuthorizeNet))
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
                            ->helperText('Used for Accept.js tokenization in the browser. Not encrypted.')
                            ->maxLength(512),
                        TextInput::make('authnet_signature_key')
                            ->label('Webhook Signature Key')
                            ->helperText('HMAC key for verifying inbound Authorize.Net webhooks. Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512),
                        Toggle::make('cim_enabled')
                            ->label('Enable CIM (Customer Information Manager)')
                            ->helperText('Enables customer vault for stored payment methods.')
                            ->columnSpanFull(),
                    ]),

                // ── Stripe credentials ────────────────────────────
                Section::make('Stripe credentials')
                    ->description('Stripe API keys from your Stripe Dashboard → Developers → API keys.')
                    ->visible(fn (Get $get): bool => self::isGateway($get, GatewayProvider::Stripe))
                    ->columns(2)
                    ->components([
                        TextInput::make('stripe_secret_key')
                            ->label('Secret Key')
                            ->helperText('Starts with sk_live_ or sk_test_. Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->columnSpanFull(),
                        TextInput::make('stripe_publishable_key')
                            ->label('Publishable Key')
                            ->helperText('Starts with pk_live_ or pk_test_. Safe to expose in browser (Stripe Elements).')
                            ->maxLength(512)
                            ->columnSpanFull(),
                        TextInput::make('stripe_webhook_secret')
                            ->label('Webhook Signing Secret')
                            ->helperText('Starts with whsec_. Used to verify inbound Stripe webhook signatures. Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->columnSpanFull(),
                    ]),

                // ── Square credentials ────────────────────────────
                Section::make('Square credentials')
                    ->description('Square credentials from your Square Developer Dashboard.')
                    ->visible(fn (Get $get): bool => self::isGateway($get, GatewayProvider::Square))
                    ->columns(2)
                    ->components([
                        TextInput::make('square_access_token')
                            ->label('Access Token')
                            ->helperText('Production or sandbox bearer token for server-side API calls. Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->columnSpanFull(),
                        TextInput::make('square_application_id')
                            ->label('Application ID')
                            ->helperText('Client-side ID for the Square Web Payments SDK. Not encrypted.')
                            ->maxLength(128),
                        TextInput::make('square_location_id')
                            ->label('Location ID')
                            ->helperText('Required on Payments / Refunds API calls. Not encrypted.')
                            ->maxLength(64),
                        TextInput::make('square_webhook_signature_key')
                            ->label('Webhook Signature Key')
                            ->helperText('HMAC-SHA256 key for verifying inbound Square webhook payloads. Stored encrypted.')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->columnSpanFull(),
                    ]),

                // ── Capabilities ──────────────────────────────────
                Section::make('Capabilities')
                    ->columns(3)
                    ->components([
                        Toggle::make('allows_recurring_payments')->default(false),
                        Toggle::make('allows_rx_processing')->default(true),
                        Toggle::make('allows_card_present')->default(false),
                        Toggle::make('allows_card_not_present')->default(true),
                        Toggle::make('supports_public_checkout')->default(true),
                    ]),

                // ── Surcharge ─────────────────────────────────────
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

                // ── Volume & routing ──────────────────────────────
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
                        TextInput::make('gateway_endpoint_url')
                            ->label('Custom Gateway Endpoint URL')
                            ->url()
                            ->placeholder('https://secure.nmi.com/api/transact.php')
                            ->helperText('Override the default API endpoint — useful for NMI sandbox proxies or on-premise deployments.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
