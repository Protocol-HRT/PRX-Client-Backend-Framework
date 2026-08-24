<?php

namespace App\Actions\Commerce;

use App\Actions\Concerns\Transacts;
use App\Data\Commerce\OrderItemData;
use App\Data\Commerce\UpsertOrderData;
use App\Models\Commerce\Encounter;
use App\Models\Commerce\Order;
use Spatie\LaravelData\DataCollection;

/**
 * Idempotent upsert by prescribe_rx_order_id. Items are reconciled by
 * (prescribe_rx_product_id || prescribe_rx_product_number || sku) — a
 * subsequent webhook for the same order replaces the items list rather
 * than appending.
 */
class UpsertOrderAction
{
    use Transacts;

    public function execute(UpsertOrderData $data): Order
    {
        return $this->tx(function () use ($data) {
            $encounter = Encounter::query()
                ->where('prescribe_rx_encounter_id', $data->prescribe_rx_encounter_id)
                ->first();

            $order = Order::query()->updateOrCreate(
                ['prescribe_rx_order_id' => $data->prescribe_rx_order_id],
                [
                    'encounter_id' => $encounter?->id,
                    'prescribe_rx_order_number' => $data->prescribe_rx_order_number,
                    'status' => $data->status,
                    'subtotal' => $data->subtotal,
                    'tax_amount' => $data->tax_amount,
                    'shipping_amount' => $data->shipping_amount,
                    'discount_amount' => $data->discount_amount,
                    'total_amount' => $data->total_amount,
                    'currency' => $data->currency,
                    'shipping_address' => $data->shipping_address,
                    'billing_address' => $data->billing_address,
                    'placed_at' => $data->placed_at,
                    'shipped_at' => $data->shipped_at,
                    'delivered_at' => $data->delivered_at,
                    'cancelled_at' => $data->cancelled_at,
                    'refunded_at' => $data->refunded_at,
                    'metadata' => $data->metadata,
                ],
            );

            $this->syncItems($order, $data->items);

            return $order->fresh(['items']);
        });
    }

    /**
     * @param  DataCollection<int, OrderItemData>|array<int, OrderItemData>  $items
     */
    private function syncItems(Order $order, $items): void
    {
        $rows = $items instanceof DataCollection ? $items->toArray() : collect($items)
            ->map(fn ($item) => is_array($item) ? $item : $item->toArray())
            ->all();

        // Replace strategy — drop existing, recreate. Order items are an
        // immutable snapshot of what PRX sent at this webhook tick.
        $order->items()->delete();
        foreach ($rows as $row) {
            $order->items()->create([
                'prescribe_rx_product_id' => $row['prescribe_rx_product_id'] ?? null,
                'prescribe_rx_product_number' => $row['prescribe_rx_product_number'] ?? null,
                'name' => $row['name'] ?? null,
                'sku' => $row['sku'] ?? null,
                'quantity' => $row['quantity'] ?? 1,
                'unit_price' => $row['unit_price'] ?? null,
                'line_total' => $row['line_total'] ?? null,
                'billing_period' => $row['billing_period'] ?? null,
                'metadata' => $row['metadata'] ?? null,
            ]);
        }
    }
}
