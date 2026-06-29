<?php

namespace Database\Factories\Commerce;

use App\Enums\ShipmentStatus;
use App\Models\Commerce\OrderShipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderShipment>
 */
class OrderShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prescribe_rx_shipment_id' => fake()->uuid(),
            'status' => ShipmentStatus::Shipped,
            'carrier' => fake()->randomElement(['USPS', 'UPS', 'FedEx', 'DHL']),
            'tracking_number' => strtoupper(fake()->bothify('??###########')),
            'tracking_url' => fake()->url(),
            'fulfillment_center' => 'FC-'.fake()->numerify('##'),
            'shipped_at' => now()->subDay(),
        ];
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => ShipmentStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }
}
