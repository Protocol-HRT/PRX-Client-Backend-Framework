<?php

namespace App\Filament\Resources\Commerce\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->components([
                        TextInput::make('uuid')
                            ->disabled()
                            ->dehydrated(false)
                            ->hintIcon(Heroicon::InformationCircle, 'Internal UUID for this order. Read-only.'),
                        TextInput::make('prescribe_rx_order_id')
                            ->label('PRX order ID')
                            ->disabled()->dehydrated(false)->copyable()
                            ->hintIcon(Heroicon::InformationCircle, 'UUID of the linked order in Prescribe-Rx. Populated automatically via webhook.'),
                        TextInput::make('prescribe_rx_order_number')
                            ->label('PRX order number')
                            ->disabled()->dehydrated(false)->copyable()
                            ->hintIcon(Heroicon::InformationCircle, 'Human-readable order number from Prescribe-Rx. Populated automatically via webhook.'),
                        Select::make('status')
                            ->options(OrderStatus::class)
                            ->required()
                            ->hintIcon(Heroicon::InformationCircle, 'Current status of this order in the fulfillment workflow.')
                            ->native(false),
                        TextInput::make('currency')
                            ->maxLength(3)
                            ->hintIcon(Heroicon::InformationCircle, 'ISO 4217 currency code, e.g. USD. Used to format monetary values in this order.'),
                    ]),
                Section::make('Totals')
                    ->columns(3)
                    ->components([
                        TextInput::make('subtotal')->numeric()->prefix('$')->disabled()->dehydrated(false),
                        TextInput::make('tax_amount')->numeric()->prefix('$')->disabled()->dehydrated(false),
                        TextInput::make('shipping_amount')->numeric()->prefix('$')->disabled()->dehydrated(false),
                        TextInput::make('discount_amount')->numeric()->prefix('$')->disabled()->dehydrated(false),
                        TextInput::make('total_amount')->numeric()->prefix('$')->disabled()->dehydrated(false),
                    ]),
                Section::make('Shipping address')
                    ->collapsed()
                    ->components([
                        KeyValue::make('shipping_address')
                            ->columnSpanFull()
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                Section::make('Lifecycle')
                    ->columns(2)
                    ->components([
                        DateTimePicker::make('placed_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the order was placed.'),
                        DateTimePicker::make('shipped_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the order was shipped.'),
                        DateTimePicker::make('delivered_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the order was delivered.'),
                        DateTimePicker::make('cancelled_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the order was cancelled.'),
                        DateTimePicker::make('refunded_at')
                            ->hintIcon(Heroicon::InformationCircle, 'Timestamp when the order was refunded.'),
                    ]),
            ]);
    }
}
