<?php

namespace Tests\Feature\Api\V1\Orders;

use App\Enums\EncounterStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Commerce\Encounter;
use App\Models\Commerce\Order;
use App\Models\Commerce\OrderShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescribeRxWebhookTest extends TestCase
{
    use RefreshDatabase;

    // ── Signature verification ────────────────────────────────────────────

    public function test_webhook_accepts_unsigned_payload_in_local_env(): void
    {
        $order = Order::factory()->create(['prescribe_rx_order_id' => 'prx-order-001']);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.updated',
            'order_id' => 'prx-order-001',
            'status' => 'processing',
        ])->assertOk();
    }

    public function test_webhook_rejects_invalid_signature_when_secret_set(): void
    {
        config(['services.prescribe_rx.webhook_secret' => 'test-secret']);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.updated',
            'order_id' => 'prx-order-001',
            'status' => 'processing',
        ])->assertUnauthorized();
    }

    public function test_webhook_accepts_valid_hmac_signature(): void
    {
        config(['services.prescribe_rx.webhook_secret' => 'test-secret']);
        $order = Order::factory()->create(['prescribe_rx_order_id' => 'prx-order-001']);

        $body = json_encode(['event' => 'order.updated', 'order_id' => 'prx-order-001', 'status' => 'processing']);
        $sig = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

        $this->postJson(
            '/api/v1/webhooks/prescribe-rx',
            ['event' => 'order.updated', 'order_id' => 'prx-order-001', 'status' => 'processing'],
            ['X-PRX-Signature' => $sig]
        )->assertOk();
    }

    // ── Order status sync ─────────────────────────────────────────────────

    public function test_webhook_updates_order_status(): void
    {
        $order = Order::factory()->pending()->create(['prescribe_rx_order_id' => 'prx-order-123']);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.updated',
            'order_id' => 'prx-order-123',
            'status' => 'processing',
        ])->assertOk();

        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_webhook_sets_shipped_at_on_first_shipped_event(): void
    {
        $order = Order::factory()->create([
            'prescribe_rx_order_id' => 'prx-order-shipped',
            'status' => OrderStatus::Pending,
            'shipped_at' => null,
        ]);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.shipped',
            'order_id' => 'prx-order-shipped',
            'status' => 'shipped',
        ])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Shipped, $fresh->status);
        $this->assertNotNull($fresh->shipped_at);
    }

    public function test_webhook_does_not_overwrite_existing_shipped_at(): void
    {
        $original = now()->subDays(2);
        $order = Order::factory()->shipped()->create([
            'prescribe_rx_order_id' => 'prx-order-reshipped',
            'shipped_at' => $original,
        ]);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.shipped',
            'order_id' => 'prx-order-reshipped',
            'status' => 'shipped',
        ])->assertOk();

        $this->assertEquals($original->toDateTimeString(), $order->fresh()->shipped_at->toDateTimeString());
    }

    public function test_webhook_sets_cancelled_at_on_cancellation(): void
    {
        $order = Order::factory()->pending()->create(['prescribe_rx_order_id' => 'prx-order-cancel']);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.cancelled',
            'order_id' => 'prx-order-cancel',
            'status' => 'cancelled',
        ])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Cancelled, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }

    // ── Encounter fallback lookup ─────────────────────────────────────────

    public function test_webhook_finds_order_via_encounter_when_prx_order_id_not_yet_set(): void
    {
        $encounter = Encounter::factory()->create(['prescribe_rx_encounter_id' => 'enc-abc']);
        // Simulate checkout-created order: no PRX IDs yet (assigned via webhook)
        $order = Order::factory()->create([
            'encounter_id' => $encounter->id,
            'prescribe_rx_order_id' => null,
            'prescribe_rx_order_number' => null,
            'status' => OrderStatus::Pending,
        ]);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.updated',
            'order_id' => 'prx-order-new',
            'order_number' => 'RX-999',
            'encounter_id' => 'enc-abc',
            'status' => 'processing',
        ])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('prx-order-new', $fresh->prescribe_rx_order_id);
        $this->assertSame('RX-999', $fresh->prescribe_rx_order_number);
        $this->assertSame(OrderStatus::Processing, $fresh->status);
    }

    public function test_webhook_silently_ignores_unknown_order(): void
    {
        // No matching order or encounter — should return 200 (accepted), not error
        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.updated',
            'order_id' => 'unknown-prx-id',
            'encounter_id' => 'unknown-enc-id',
            'status' => 'processing',
        ])->assertOk();
    }

    // ── Encounter status sync ─────────────────────────────────────────────

    public function test_webhook_updates_encounter_status(): void
    {
        $encounter = Encounter::factory()->create([
            'prescribe_rx_encounter_id' => 'enc-status-test',
            'status' => EncounterStatus::Submitted,
        ]);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.updated',
            'encounter_id' => 'enc-status-test',
            'encounter_status' => 'approved',
        ])->assertOk();

        $this->assertSame(EncounterStatus::Approved, $encounter->fresh()->status);
    }

    // ── Shipment sync ─────────────────────────────────────────────────────

    public function test_webhook_creates_shipment_from_payload(): void
    {
        $order = Order::factory()->create(['prescribe_rx_order_id' => 'prx-order-ship']);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.shipped',
            'order_id' => 'prx-order-ship',
            'status' => 'shipped',
            'shipments' => [[
                'shipment_id' => 'prx-shipment-001',
                'status' => 'shipped',
                'carrier' => 'USPS',
                'tracking_number' => '9400111899223418527401',
                'tracking_url' => 'https://tracking.usps.com/?n=9400111899223418527401',
                'shipped_at' => now()->toISOString(),
            ]],
        ])->assertOk();

        $shipment = OrderShipment::where('prescribe_rx_shipment_id', 'prx-shipment-001')->first();
        $this->assertNotNull($shipment);
        $this->assertSame('USPS', $shipment->carrier);
        $this->assertSame('9400111899223418527401', $shipment->tracking_number);
        $this->assertSame($order->id, $shipment->order_id);
    }

    public function test_webhook_updates_existing_shipment_idempotently(): void
    {
        $order = Order::factory()->create(['prescribe_rx_order_id' => 'prx-order-idempotent']);
        OrderShipment::factory()->create([
            'order_id' => $order->id,
            'prescribe_rx_shipment_id' => 'prx-ship-idem',
            'status' => ShipmentStatus::Shipped,
        ]);

        $this->postJson('/api/v1/webhooks/prescribe-rx', [
            'event' => 'order.delivered',
            'order_id' => 'prx-order-idempotent',
            'status' => 'delivered',
            'shipments' => [[
                'shipment_id' => 'prx-ship-idem',
                'status' => 'delivered',
                'delivered_at' => now()->toISOString(),
            ]],
        ])->assertOk();

        $this->assertSame(
            ShipmentStatus::Delivered,
            OrderShipment::where('prescribe_rx_shipment_id', 'prx-ship-idem')->first()->status
        );
    }
}
