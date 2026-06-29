<?php

namespace Database\Factories\Commerce;

use App\Enums\OrderStatus;
use App\Models\Commerce\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prescribe_rx_order_id' => fake()->uuid(),
            'prescribe_rx_order_number' => 'RX-'.fake()->numerify('######'),
            'status' => OrderStatus::Pending,
            'subtotal' => fake()->randomFloat(2, 50, 500),
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => fake()->randomFloat(2, 50, 500),
            'currency' => 'USD',
            'placed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending]);
    }

    public function shipped(): static
    {
        return $this->state([
            'status' => OrderStatus::Shipped,
            'shipped_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => OrderStatus::Delivered,
            'shipped_at' => now()->subDays(3),
            'delivered_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
