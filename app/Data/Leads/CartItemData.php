<?php

namespace App\Data\Leads;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CartItemData extends Data
{
    public function __construct(
        #[Required, In(['product', 'package', 'plan'])]
        public string $resource_type,
        #[Required]
        public int $resource_id,
        #[Required, Min(1)]
        public int $quantity = 1,
        public ?string $name = null,
        public ?float $unit_price = null,
        public ?string $price_suffix = null,
        public ?string $billing_period = null,
        public ?string $prescribe_rx_id = null,
        public ?string $prescribe_rx_number = null,
    ) {}
}
