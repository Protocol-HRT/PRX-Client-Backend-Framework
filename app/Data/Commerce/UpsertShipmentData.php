<?php

namespace App\Data\Commerce;

use App\Enums\ShipmentStatus;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class UpsertShipmentData extends Data
{
    /**
     * @param  array<int, array{prescribe_rx_product_id?: ?string, prescribe_rx_product_number?: ?string, quantity: int}>  $items
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        #[Required, Max(64)]
        public string $prescribe_rx_order_id,
        #[WithCast(EnumCast::class)]
        public ShipmentStatus $status = ShipmentStatus::Pending,
        #[Max(64)]
        public ?string $prescribe_rx_shipment_id = null,
        #[Max(32)]
        public ?string $carrier = null,
        #[Max(128)]
        public ?string $tracking_number = null,
        #[Max(2048)]
        public ?string $tracking_url = null,
        #[Max(64)]
        public ?string $fulfillment_center = null,
        public ?string $shipped_at = null,
        public ?string $delivered_at = null,
        public ?string $exception_at = null,
        public ?string $exception_reason = null,
        public array $items = [],
        public ?array $metadata = null,
    ) {}
}
