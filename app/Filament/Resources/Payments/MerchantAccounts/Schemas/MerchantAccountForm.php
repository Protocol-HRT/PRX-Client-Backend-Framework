<?php

namespace App\Filament\Resources\Payments\MerchantAccounts\Schemas;

use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
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
                            ->maxLength(191)
                            ->columnSpan(1),
                        Select::make('gateway_provider')
                            ->options(GatewayProvider::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpan(1),
                        Select::make('environment')
                            ->options(GatewayEnvironment::class)
                            ->required()
                            ->native(false)
                            ->default(GatewayEnvironment::Sandbox->value),
                        Toggle::make('is_active')
                            ->default(true),
                        Toggle::make('is_default')
                            ->helperText('Only one account should be set as default.'),
                    ]),

                Section::make('NMI credentials')
                    ->visible(fn (Get $get): bool => $get('gateway_provider') === GatewayProvider::Nmi->value)
                    ->components([
                        TextInput::make('nmi_security_key')
                            ->label('Security Key')
                            ->password()
                            ->revealable()
                            ->maxLength(512)
                            ->columnSpanFull(),
                    ]),

                Section::make('Authorize.Net credentials')
                    ->visible(fn (Get $get): bool => $get('gateway_provider') === GatewayProvider::AuthorizeNet->value)
                    ->columns(2)
                    ->components([
                        TextInput::make('authnet_api_login_id')
                            ->label('API Login ID')
                            ->password()
                            ->revealable()
                            ->maxLength(512),
                        TextInput::make('authnet_transaction_key')
                            ->label('Transaction Key')
                            ->password()
                            ->revealable()
                            ->maxLength(512),
                        TextInput::make('authnet_public_client_key')
                            ->label('Public Client Key')
                            ->helperText('Used for Accept.js tokenization in the browser. Safe to expose.')
                            ->maxLength(512)
                            ->columnSpanFull(),
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
                            ->placeholder('Unlimited')
                            ->helperText('Leave blank for no cap.'),
                        Toggle::make('auto_disable_at_limit')
                            ->label('Auto-disable when limit reached'),
                        Toggle::make('allows_recurring_payments'),
                    ]),
            ]);
    }
}
