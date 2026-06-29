<?php

namespace Database\Factories\Commerce;

use App\Models\Commerce\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 20, 200);

        return [
            'prescribe_rx_product_id' => fake()->uuid(),
            'prescribe_rx_product_number' => 'PRX-'.fake()->numerify('####'),
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('??-####')),
            'quantity' => 1,
            'unit_price' => $price,
            'line_total' => $price,
        ];
    }
}
