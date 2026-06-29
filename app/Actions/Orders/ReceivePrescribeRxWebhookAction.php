<?php

namespace App\Actions\Orders;

use App\Enums\EncounterStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Commerce\Encounter;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderShipment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceivePrescribeRxWebhookAction
{
    /**
     * Process an inbound Prescribe-Rx webhook payload.
     *
     * Expected payload shape (mirrors PRX order-sync events):
     * {
     *   "event":      "order.updated" | "order.shipped" | "order.delivered" | "order.cancelled",
     *   "order_id":   "prx-uuid",
     *   "order_number": "RX-12345",
     *   "status":     "processing" | "shipped" | "delivered" | "cancelled",
     *   "encounter_id": "prx-encounter-uuid",
     *   "encounter_status": "approved" | "completed" | "cancelled",
     *   "shipments": [
     *     {
     *       "shipment_id": "prx-shipment-uuid",
     *       "status":      "shipped" | "delivered",
     *       "carrier":     "USPS",
     *       "tracking_number": "...",
     *       "tracking_url":    "...",
     *       "shipped_at":  "ISO8601",
     *       "delivered_at": "ISO8601"
     *     }
     *   ]
     * }
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $order = $this->resolveOrder($payload);
            $this->syncOrder($order, $payload);
            $this->syncEncounter($payload);
            $this->syncShipments($order, $payload);
        });
    }

    /**
     * Resolve the local Order for this webhook payload.
     *
     * Two-step lookup to handle the async timing gap between checkout and PRX order creation:
     *   1. By prescribe_rx_order_id — fast path for re-delivered or subsequent webhooks.
     *   2. Via encounter → orders — first-delivery path when Order was created at checkout
     *      before PRX had assigned its order_id. Writes the PRX order_id back on first match
     *      so subsequent webhooks resolve via step 1.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveOrder(array $payload): ?Order
    {
        $prxOrderId = Arr::get($payload, 'order_id');
        if (blank($prxOrderId)) {
            return null;
        }

        $order = Order::where('prescribe_rx_order_id', $prxOrderId)->first();

        if (! $order) {
            $prxEncounterId = Arr::get($payload, 'encounter_id');
            if (filled($prxEncounterId)) {
                $encounter = Encounter::where('prescribe_rx_encounter_id', $prxEncounterId)->first();
                $order = $encounter?->orders()->latest()->first();
            }
        }

        if (! $order) {
            Log::warning('PRX webhook: no matching local order', [
                'prescribe_rx_order_id' => $prxOrderId,
                'prescribe_rx_encounter_id' => Arr::get($payload, 'encounter_id'),
            ]);

            return null;
        }

        // Backfill PRX IDs on the order so future webhooks use the fast path.
        $backfill = array_filter([
            'prescribe_rx_order_id' => blank($order->prescribe_rx_order_id) ? $prxOrderId : null,
            'prescribe_rx_order_number' => blank($order->prescribe_rx_order_number)
                ? Arr::get($payload, 'order_number')
                : null,
        ]);

        if ($backfill) {
            $order->update($backfill);
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncOrder(?Order $order, array $payload): void
    {
        if (! $order) {
            return;
        }

        $updates = [];

        $status = OrderStatus::tryFrom((string) Arr::get($payload, 'status'));
        if ($status) {
            $updates['status'] = $status;
        }

        if ($status === OrderStatus::Shipped && ! $order->shipped_at) {
            $updates['shipped_at'] = now();
        }

        if ($status === OrderStatus::Delivered && ! $order->delivered_at) {
            $updates['delivered_at'] = now();
        }

        if ($status === OrderStatus::Cancelled && ! $order->cancelled_at) {
            $updates['cancelled_at'] = now();
        }

        if ($updates) {
            $order->update($updates);
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function syncEncounter(array $payload): void
    {
        $prxEncounterId = Arr::get($payload, 'encounter_id');
        if (blank($prxEncounterId)) {
            return;
        }

        $encounter = Encounter::where('prescribe_rx_encounter_id', $prxEncounterId)->first();
        if (! $encounter) {
            return;
        }

        $status = EncounterStatus::tryFrom((string) Arr::get($payload, 'encounter_status'));
        if ($status) {
            $encounter->update(['status' => $status]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncShipments(?Order $order, array $payload): void
    {
        if (! $order) {
            return;
        }

        $shipments = Arr::get($payload, 'shipments', []);
        if (empty($shipments)) {
            return;
        }

        foreach ($shipments as $shipmentData) {
            $prxShipmentId = Arr::get($shipmentData, 'shipment_id');
            if (blank($prxShipmentId)) {
                continue;
            }

            $shipment = OrderShipment::firstOrNew([
                'prescribe_rx_shipment_id' => $prxShipmentId,
            ]);

            $status = ShipmentStatus::tryFrom((string) Arr::get($shipmentData, 'status'));

            $shipment->fill([
                'order_id' => $order->id,
                'status' => $status ?? $shipment->status,
                'carrier' => Arr::get($shipmentData, 'carrier'),
                'tracking_number' => Arr::get($shipmentData, 'tracking_number'),
                'tracking_url' => Arr::get($shipmentData, 'tracking_url'),
                'shipped_at' => Arr::get($shipmentData, 'shipped_at') ?? $shipment->shipped_at,
                'delivered_at' => Arr::get($shipmentData, 'delivered_at') ?? $shipment->delivered_at,
            ])->save();
        }
    }
}
