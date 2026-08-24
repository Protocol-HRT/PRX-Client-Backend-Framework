<?php

namespace App\Data\Cart;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class CartViewData extends Data
{
    /**
     * @param  DataCollection<int, CartItemViewData>  $items
     */
    public function __construct(
        #[DataCollectionOf(CartItemViewData::class)]
        public DataCollection $items,
        public int $total_quantity,
        public float $subtotal,
        public bool $has_unavailable_items,
    ) {}

    public function isEmpty(): bool
    {
        return $this->total_quantity === 0;
    }
}
