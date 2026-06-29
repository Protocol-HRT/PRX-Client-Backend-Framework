<?php

namespace App\Data\Checkout;

use Spatie\LaravelData\Data;

class CheckoutResultData extends Data
{
    public function __construct(
        public string $order_uuid,
        public string $checkout_path,
        /** PRX encounter details — null for local checkout path. */
        public ?array $prescribe_rx = null,
    ) {}
}
