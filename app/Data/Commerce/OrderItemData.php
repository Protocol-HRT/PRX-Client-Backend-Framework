<?php

namespace App\Data\Commerce;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class OrderItemData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        #[Max(64)]
        public ?string $prescribe_rx_product_id = null,
        #[Max(64)]
        public ?string $prescribe_rx_product_number = null,
        public ?string $name = null,
        #[Max(64)]
        public ?string $sku = null,
        #[Min(1)]
        public int $quantity = 1,
        public ?float $unit_price = null,
        public ?float $line_total = null,
        #[Max(32)]
        public ?string $billing_period = null,
        public ?array $metadata = null,
    ) {}
}
