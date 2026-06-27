<?php

namespace App\Data\Commerce;

use App\Enums\OrderStatus;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class UpsertOrderData extends Data
{
    /**
     * @param  DataCollection<int, OrderItemData>|array<int, OrderItemData>  $items
     * @param  array<string, mixed>|null  $shipping_address
     * @param  array<string, mixed>|null  $billing_address
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        #[Required, Max(64)]
        public string $prescribe_rx_order_id,
        #[Required, Max(64)]
        public string $prescribe_rx_encounter_id,
        #[WithCast(EnumCast::class)]
        public OrderStatus $status = OrderStatus::Pending,
        #[Max(64)]
        public ?string $prescribe_rx_order_number = null,
        public ?float $subtotal = null,
        public ?float $tax_amount = null,
        public ?float $shipping_amount = null,
        public ?float $discount_amount = null,
        public ?float $total_amount = null,
        #[Max(3)]
        public string $currency = 'USD',
        public ?array $shipping_address = null,
        public ?array $billing_address = null,
        public ?string $placed_at = null,
        public ?string $shipped_at = null,
        public ?string $delivered_at = null,
        public ?string $cancelled_at = null,
        public ?string $refunded_at = null,
        #[DataCollectionOf(OrderItemData::class)]
        public array|DataCollection $items = [],
        public ?array $metadata = null,
    ) {}
}
