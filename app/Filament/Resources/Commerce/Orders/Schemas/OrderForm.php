<?php

namespace App\Filament\Resources\Commerce\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->components([
                        TextInput::make('uuid')->disabled()->dehydrated(false),
                        TextInput::make('prescribe_rx_order_id')
                            ->label('PRX order ID')
                            ->disabled()->dehydrated(false)->copyable(),
                        TextInput::make('prescribe_rx_order_number')
                            ->label('PRX order number')
                            ->disabled()->dehydrated(false)->copyable(),
                        Select::make('status')
                            ->options(OrderStatus::class)
                            ->required()
                            ->native(false),
                        TextInput::make('currency')->maxLength(3),
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
                        DateTimePicker::make('placed_at'),
                        DateTimePicker::make('shipped_at'),
                        DateTimePicker::make('delivered_at'),
                        DateTimePicker::make('cancelled_at'),
                        DateTimePicker::make('refunded_at'),
                    ]),
            ]);
    }
}
