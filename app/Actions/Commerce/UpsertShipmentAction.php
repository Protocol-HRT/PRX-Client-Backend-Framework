<?php

namespace App\Actions\Commerce;

use App\Actions\Concerns\Transacts;
use App\Data\Commerce\UpsertShipmentData;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderShipment;

/**
 * Upserts a single shipment (one fulfillment center → one tracking number).
 *
 * Lookup keys, in priority order:
 *   1) prescribe_rx_shipment_id (preferred — PRX-side identifier)
 *   2) (carrier, tracking_number) pair — natural key when PRX doesn't
 *      surface its own shipment id yet
 *
 * Item reconciliation: matches on prescribe_rx_product_id or
 * prescribe_rx_product_number against the order's existing line items.
 * Untracked items are silently ignored (they may belong to a different
 * shipment).
 */
class UpsertShipmentAction
{
    use Transacts;

    public function execute(UpsertShipmentData $data): ?OrderShipment
    {
        return $this->tx(function () use ($data) {
            $order = Order::query()
                ->where('prescribe_rx_order_id', $data->prescribe_rx_order_id)
                ->first();

            if (! $order) {
                // Order webhook hasn't landed yet — caller should re-queue.
                return null;
            }

            $shipment = $this->lookupOrCreate($order, $data);

            $shipment->fill([
                'status' => $data->status,
                'carrier' => $data->carrier,
                'tracking_number' => $data->tracking_number,
                'tracking_url' => $data->tracking_url,
                'fulfillment_center' => $data->fulfillment_center,
                'shipped_at' => $data->shipped_at,
                'delivered_at' => $data->delivered_at,
                'exception_at' => $data->exception_at,
                'exception_reason' => $data->exception_reason,
                'metadata' => $data->metadata,
            ])->save();

            $this->syncShipmentItems($order, $shipment, $data->items);

            return $shipment->fresh(['items']);
        });
    }

    private function lookupOrCreate(Order $order, UpsertShipmentData $data): OrderShipment
    {
        if ($data->prescribe_rx_shipment_id) {
            return OrderShipment::firstOrNew([
                'prescribe_rx_shipment_id' => $data->prescribe_rx_shipment_id,
            ], ['order_id' => $order->id]);
        }

        if ($data->carrier && $data->tracking_number) {
            return $order->shipments()->firstOrNew([
                'carrier' => $data->carrier,
                'tracking_number' => $data->tracking_number,
            ]);
        }

        // No identifier at all — create a fresh shipment row each time.
        // Caller probably needs to send shipment_id eventually.
        return $order->shipments()->newModelInstance();
    }

    /**
     * @param  array<int, array{prescribe_rx_product_id?: ?string, prescribe_rx_product_number?: ?string, quantity: int}>  $items
     */
    private function syncShipmentItems(Order $order, OrderShipment $shipment, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $sync = [];
        foreach ($items as $row) {
            $orderItem = $order->items
                ->first(function ($item) use ($row) {
                    if (! empty($row['prescribe_rx_product_id'])) {
                        return $item->prescribe_rx_product_id === $row['prescribe_rx_product_id'];
                    }
                    if (! empty($row['prescribe_rx_product_number'])) {
                        return $item->prescribe_rx_product_number === $row['prescribe_rx_product_number'];
                    }

                    return false;
                });

            if ($orderItem) {
                $sync[$orderItem->id] = ['quantity' => $row['quantity'] ?? 1];
            }
        }

        $shipment->items()->sync($sync);
    }
}
