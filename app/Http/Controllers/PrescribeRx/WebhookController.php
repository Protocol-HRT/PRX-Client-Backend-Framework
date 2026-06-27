<?php

namespace App\Http\Controllers\PrescribeRx;

use App\Actions\Commerce\UpsertEncounterAction;
use App\Actions\Commerce\UpsertOrderAction;
use App\Actions\Commerce\UpsertShipmentAction;
use App\Actions\Leads\MarkLeadCompletedAction;
use App\Actions\Leads\MarkLeadHandedOffAction;
use App\Data\Commerce\UpsertEncounterData;
use App\Data\Commerce\UpsertOrderData;
use App\Data\Commerce\UpsertShipmentData;
use App\Enums\EncounterStatus;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * prescribe-rx webhook receiver.
 *
 * Signature verification happens upstream in VerifyPrescribeRxSignature
 * middleware — this controller assumes the request is authentic.
 *
 * Event router pattern: dispatch by `event` field on the body. Unknown
 * events are logged + returned 200 so PRX doesn't replay them forever.
 *
 * Handlers MUST be idempotent — webhook delivery is at-least-once.
 *
 * No clinical answers are persisted. The handlers only consume the
 * fields documented in their respective DTOs; everything else is
 * ignored. If PRX adds clinical data to event payloads in the future
 * the DTO mapping naturally drops it.
 */
class WebhookController
{
    public function __construct(
        protected UpsertEncounterAction $upsertEncounter,
        protected UpsertOrderAction $upsertOrder,
        protected UpsertShipmentAction $upsertShipment,
        protected MarkLeadHandedOffAction $markLeadHandedOff,
        protected MarkLeadCompletedAction $markLeadCompleted,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? '');
        $data = (array) ($payload['data'] ?? []);

        try {
            match (true) {
                str_starts_with($event, 'encounter.') => $this->handleEncounter($event, $data, $payload),
                str_starts_with($event, 'order.') => $this->handleOrder($event, $data),
                str_starts_with($event, 'shipment.') => $this->handleShipment($event, $data),
                default => Log::info('prx-webhook: unhandled event', ['event' => $event]),
            };
        } catch (Throwable $e) {
            // Log but return 200 — webhook is at-least-once. We'd rather
            // PRX consider it delivered and rely on our internal alerting
            // / replay mechanism than have them retry-storm.
            Log::error('prx-webhook: handler threw', [
                'event' => $event,
                'message' => $e->getMessage(),
                'trace_id' => Arr::get($payload, 'id'),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload
     */
    private function handleEncounter(string $event, array $data, array $payload): void
    {
        $status = $this->resolveEncounterStatus($event, $data);

        $dto = UpsertEncounterData::from([
            'prescribe_rx_encounter_id' => Arr::get($data, 'id') ?? Arr::get($data, 'encounter_id'),
            'lead_uuid' => Arr::get($payload, 'metadata.lead_uuid')
                ?? Arr::get($data, 'metadata.lead_uuid'),
            'prescribe_rx_patient_id' => Arr::get($data, 'patient_id'),
            'prescribe_rx_encounter_type_id' => Arr::get($data, 'encounter_type_id'),
            'status' => $status->value,
            'submitted_at' => Arr::get($data, 'submitted_at'),
            'reviewed_at' => Arr::get($data, 'reviewed_at'),
            'completed_at' => Arr::get($data, 'completed_at'),
            'cancelled_at' => Arr::get($data, 'cancelled_at'),
            'total_amount' => Arr::get($data, 'total_amount'),
            'is_sandbox' => (bool) Arr::get($data, 'is_sandbox', false),
            'metadata' => $this->scrubMetadata(Arr::get($data, 'metadata')),
        ]);

        $encounter = $this->upsertEncounter->execute($dto);

        // Cross-update the originating Lead so admin sees status flow.
        $leadUuid = Arr::get($payload, 'metadata.lead_uuid')
            ?? Arr::get($data, 'metadata.lead_uuid');
        $lead = $leadUuid ? Lead::query()->where('uuid', $leadUuid)->first() : null;
        if (! $lead) {
            return;
        }

        if ($event === 'encounter.created' || $event === 'encounter.submitted') {
            $this->markLeadHandedOff->execute(
                $lead,
                $encounter->prescribe_rx_encounter_id,
                $encounter->prescribe_rx_patient_id,
            );
        }

        if ($event === 'encounter.completed') {
            $this->markLeadCompleted->execute($lead, [
                'encounter_id' => $encounter->prescribe_rx_encounter_id,
                'completed_at' => $encounter->completed_at?->toIso8601String(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleOrder(string $event, array $data): void
    {
        $status = $this->resolveOrderStatus($event, $data);

        $items = collect((array) Arr::get($data, 'items', []))
            ->map(fn (array $row) => [
                'prescribe_rx_product_id' => Arr::get($row, 'product_id'),
                'prescribe_rx_product_number' => Arr::get($row, 'product_number'),
                'name' => Arr::get($row, 'name'),
                'sku' => Arr::get($row, 'sku'),
                'quantity' => (int) Arr::get($row, 'quantity', 1),
                'unit_price' => Arr::get($row, 'unit_price'),
                'line_total' => Arr::get($row, 'line_total'),
                'billing_period' => Arr::get($row, 'billing_period'),
                'metadata' => $this->scrubMetadata(Arr::get($row, 'metadata')),
            ])
            ->all();

        $dto = UpsertOrderData::from([
            'prescribe_rx_order_id' => Arr::get($data, 'id') ?? Arr::get($data, 'order_id'),
            'prescribe_rx_encounter_id' => Arr::get($data, 'encounter_id'),
            'prescribe_rx_order_number' => Arr::get($data, 'order_number'),
            'status' => $status->value,
            'subtotal' => Arr::get($data, 'subtotal'),
            'tax_amount' => Arr::get($data, 'tax_amount'),
            'shipping_amount' => Arr::get($data, 'shipping_amount'),
            'discount_amount' => Arr::get($data, 'discount_amount'),
            'total_amount' => Arr::get($data, 'total_amount'),
            'currency' => Arr::get($data, 'currency', 'USD'),
            'shipping_address' => Arr::get($data, 'shipping_address'),
            'billing_address' => Arr::get($data, 'billing_address'),
            'placed_at' => Arr::get($data, 'placed_at'),
            'shipped_at' => Arr::get($data, 'shipped_at'),
            'delivered_at' => Arr::get($data, 'delivered_at'),
            'cancelled_at' => Arr::get($data, 'cancelled_at'),
            'refunded_at' => Arr::get($data, 'refunded_at'),
            'items' => $items,
            'metadata' => $this->scrubMetadata(Arr::get($data, 'metadata')),
        ]);

        $this->upsertOrder->execute($dto);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleShipment(string $event, array $data): void
    {
        $status = $this->resolveShipmentStatus($event, $data);

        $items = collect((array) Arr::get($data, 'items', []))
            ->map(fn (array $row) => [
                'prescribe_rx_product_id' => Arr::get($row, 'product_id'),
                'prescribe_rx_product_number' => Arr::get($row, 'product_number'),
                'quantity' => (int) Arr::get($row, 'quantity', 1),
            ])
            ->all();

        $dto = UpsertShipmentData::from([
            'prescribe_rx_order_id' => Arr::get($data, 'order_id'),
            'prescribe_rx_shipment_id' => Arr::get($data, 'id'),
            'status' => $status->value,
            'carrier' => Arr::get($data, 'carrier'),
            'tracking_number' => Arr::get($data, 'tracking_number'),
            'tracking_url' => Arr::get($data, 'tracking_url'),
            'fulfillment_center' => Arr::get($data, 'fulfillment_center'),
            'shipped_at' => Arr::get($data, 'shipped_at'),
            'delivered_at' => Arr::get($data, 'delivered_at'),
            'exception_at' => Arr::get($data, 'exception_at'),
            'exception_reason' => Arr::get($data, 'exception_reason'),
            'items' => $items,
            'metadata' => $this->scrubMetadata(Arr::get($data, 'metadata')),
        ]);

        $this->upsertShipment->execute($dto);
    }

    /**
     * Defense in depth: drop any keys that look clinical from the metadata
     * pass-through. The webhook handlers should never persist these — but
     * we strip rather than trust callers (or our future selves).
     *
     * @param  mixed  $metadata
     * @return array<string, mixed>|null
     */
    private function scrubMetadata($metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $blocked = [
            'allergies', 'medications', 'conditions',
            'symptoms', 'diagnoses', 'lab_results',
            'vitals', 'medical_history', 'answers',
            'chief_complaint', 'reason_for_visit',
        ];

        return Arr::except($metadata, $blocked);
    }

    private function resolveEncounterStatus(string $event, array $data): EncounterStatus
    {
        if ($explicit = Arr::get($data, 'status')) {
            return EncounterStatus::tryFrom($explicit) ?? EncounterStatus::Pending;
        }

        return match ($event) {
            'encounter.created' => EncounterStatus::Pending,
            'encounter.submitted' => EncounterStatus::Submitted,
            'encounter.review_started' => EncounterStatus::InReview,
            'encounter.approved' => EncounterStatus::Approved,
            'encounter.denied' => EncounterStatus::Denied,
            'encounter.completed' => EncounterStatus::Completed,
            'encounter.cancelled' => EncounterStatus::Cancelled,
            default => EncounterStatus::Pending,
        };
    }

    private function resolveOrderStatus(string $event, array $data): \App\Enums\OrderStatus
    {
        if ($explicit = Arr::get($data, 'status')) {
            return \App\Enums\OrderStatus::tryFrom($explicit) ?? \App\Enums\OrderStatus::Pending;
        }

        return match ($event) {
            'order.created' => \App\Enums\OrderStatus::Pending,
            'order.processing' => \App\Enums\OrderStatus::Processing,
            'order.shipped' => \App\Enums\OrderStatus::Shipped,
            'order.partially_shipped' => \App\Enums\OrderStatus::PartiallyShipped,
            'order.delivered' => \App\Enums\OrderStatus::Delivered,
            'order.cancelled' => \App\Enums\OrderStatus::Cancelled,
            'order.refunded' => \App\Enums\OrderStatus::Refunded,
            default => \App\Enums\OrderStatus::Pending,
        };
    }

    private function resolveShipmentStatus(string $event, array $data): \App\Enums\ShipmentStatus
    {
        if ($explicit = Arr::get($data, 'status')) {
            return \App\Enums\ShipmentStatus::tryFrom($explicit) ?? \App\Enums\ShipmentStatus::Pending;
        }

        return match ($event) {
            'shipment.created', 'shipment.label_created' => \App\Enums\ShipmentStatus::LabelCreated,
            'shipment.shipped' => \App\Enums\ShipmentStatus::Shipped,
            'shipment.in_transit' => \App\Enums\ShipmentStatus::InTransit,
            'shipment.delivered' => \App\Enums\ShipmentStatus::Delivered,
            'shipment.exception' => \App\Enums\ShipmentStatus::Exception,
            'shipment.cancelled' => \App\Enums\ShipmentStatus::Cancelled,
            default => \App\Enums\ShipmentStatus::Pending,
        };
    }
}
