<?php

namespace App\Data\Cart;

use Spatie\LaravelData\Data;

/**
 * Hydrated representation of a cart line item — what the UI renders.
 * Includes the resolved model fields (name, image, price) alongside
 * the raw cart-store keys (resource_type/id, quantity).
 */
class CartItemViewData extends Data
{
    public function __construct(
        public string $resource_type,    // product|package|plan
        public int $resource_id,
        public int $quantity,
        public string $name,
        public string $slug,
        public string $url,
        public ?string $hero_image_path,
        public ?float $unit_price,        // sale_price ?: retail_price
        public ?float $line_total,        // unit_price * quantity
        public ?string $price_suffix,
        public ?string $billing_period,
        public ?string $prescribe_rx_id,
        public ?string $prescribe_rx_number,
        public bool $available = true,    // false if the underlying record was deleted/unpublished.
    ) {}
}
