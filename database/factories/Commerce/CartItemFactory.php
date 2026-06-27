<?php

namespace Database\Factories\Commerce;

use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Models\Commerce\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'cart_id' => Cart::factory(),
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'plan_id' => null,
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price_snapshot' => fake()->randomFloat(2, 49, 299),
        ];
    }
}
