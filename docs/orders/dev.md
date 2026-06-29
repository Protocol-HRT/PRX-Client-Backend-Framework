# Orders Module — Developer Guide

**Status:** Shipped 2026-06-28

---

## Data model

```
encounters
  └── hasMany → orders
                  └── hasMany → order_items
                  └── hasMany → order_shipments
                                  └── belongsToMany → order_items (via order_shipment_items, pivot: quantity)
```

### `orders`

| Column | Type | Notes |
|---|---|---|
| `uuid` | char(36) | Public route key and bearer credential. Auto-generated on create. |
| `encounter_id` | bigint nullable | FK → encounters. Null until webhook backfills on first delivery. Set at checkout. |
| `prescribe_rx_order_id` | varchar(64) nullable unique | PRX's internal order UUID. Null at checkout; backfilled by first webhook. |
| `prescribe_rx_order_number` | varchar(64) nullable | Human-readable order number (e.g. `RX-123456`). Backfilled by webhook. |
| `status` | varchar(32) | `OrderStatus` enum value. Default `pending`. |
| `subtotal` / `tax_amount` / `shipping_amount` / `discount_amount` / `total_amount` | decimal(10,2) | All nullable; populated by webhook. `subtotal` set at checkout from cart. |
| `currency` | varchar(3) | Default `USD`. |
| `shipping_address` / `billing_address` | text | Encrypted at model level (`encrypted:array`). Never exposed via API. |
| `placed_at` | timestamp | Set at checkout submission. |
| `shipped_at` / `delivered_at` / `cancelled_at` / `refunded_at` | timestamp nullable | Set once, never overwritten, by the corresponding webhook event. |

### `order_items`

| Column | Notes |
|---|---|
| `prescribe_rx_product_id` | PRX product UUID. Used for shipment item reconciliation. |
| `prescribe_rx_product_number` | PRX product number fallback. |
| `name` | Encrypted (`encrypted` cast). Product name at snapshot time. |
| `sku` | Provider SKU. |
| `quantity` | Unit count. |
| `unit_price` / `line_total` | decimal(10,2). Immutable snapshot. |
| `billing_period` | Subscription cadence (e.g. `monthly`). Null for one-time items. |

### `order_shipments`

| Column | Notes |
|---|---|
| `prescribe_rx_shipment_id` | PRX's shipment UUID. Lookup key for idempotent upsert. |
| `status` | `ShipmentStatus` enum. |
| `carrier` | `USPS`, `UPS`, `FedEx`, `DHL`. |
| `tracking_number` / `tracking_url` | Carrier tracking. |
| `fulfillment_center` | Opaque FC code from PRX. |
| `shipped_at` / `delivered_at` / `exception_at` | Set from webhook payload timestamps. |
| `exception_reason` | Carrier exception message. |

### `order_shipment_items` (pivot)

Junction between `order_shipments` and `order_items` with a `quantity` column to handle split-FC shipments where part of a quantity ships from one FC and the remainder from another.

---

## Enums

### `App\Enums\OrderStatus`
`Pending | Processing | Shipped | PartiallyShipped | Delivered | Cancelled | Refunded`

Methods: `label()`, `color()` (for Filament badges).

### `App\Enums\ShipmentStatus`
`Pending | LabelCreated | Shipped | InTransit | Delivered | Exception | Cancelled`

Methods: `label()`, `color()`.

---

## Actions

### `App\Actions\Orders\ReceivePrescribeRxWebhookAction`

Entry point for all inbound PRX webhooks. Runs in a single DB transaction.

**Three sub-methods:**

1. `resolveOrder(payload)` — Two-step order lookup:
   - Step 1: by `prescribe_rx_order_id` (fast path for re-deliveries)
   - Step 2: via `encounter → orders` (first-delivery path — checkout creates orders without PRX order ID)
   - On first match via encounter: backfills `prescribe_rx_order_id` and `prescribe_rx_order_number` so step 1 works on all future deliveries

2. `syncOrder(order, payload)` — Updates status and sets timestamp columns (`shipped_at`, `delivered_at`, `cancelled_at`) once-only (never overwrites existing timestamp).

3. `syncEncounter(payload)` — Updates the linked `Encounter.status` from `encounter_status` in the payload.

4. `syncShipments(order, payload)` — Upserts `OrderShipment` rows by `prescribe_rx_shipment_id`.

### `App\Actions\Commerce\UpsertOrderAction`

General-purpose idempotent upsert by `prescribe_rx_order_id`. Reconciles items using a **replace strategy** — drops all existing items and recreates from the payload. Used when the full order data is available (e.g. a rich order-created event). Not currently called by the webhook flow.

### `App\Actions\Commerce\UpsertShipmentAction`

Idempotent shipment upsert. Lookup priority: `prescribe_rx_shipment_id` → `(carrier, tracking_number)` pair → new row. Reconciles `order_shipment_items` pivot from `prescribe_rx_product_id` / `prescribe_rx_product_number`.

---

## API endpoints

### `GET /api/v1/orders/{uuid}`

Retrieve an order by UUID. The UUID is returned at checkout completion and treated as a bearer credential — there is no additional authentication on this endpoint.

**Auth:** Sanctum bearer token on the request (frontend client token), but no user-ownership check. UUID is the access control.

**Response `200`:**
```json
{
  "data": {
    "uuid": "01hxx...",
    "status": "shipped",
    "subtotal": "149.00",
    "tax_amount": "0.00",
    "shipping_amount": "0.00",
    "discount_amount": "0.00",
    "total_amount": "149.00",
    "currency": "USD",
    "placed_at": "2026-06-28T12:00:00.000000Z",
    "shipped_at": "2026-06-29T08:00:00.000000Z",
    "delivered_at": null,
    "cancelled_at": null,
    "prescribe_rx_order_number": "RX-123456",
    "items": [
      {
        "id": 1,
        "name": "Testosterone Cream",
        "sku": "TC-200",
        "quantity": 1,
        "unit_price": "149.00",
        "line_total": "149.00",
        "billing_period": "monthly"
      }
    ],
    "shipments": [
      {
        "id": 1,
        "status": "shipped",
        "carrier": "USPS",
        "tracking_number": "9400111899223418527401",
        "tracking_url": "https://...",
        "shipped_at": "2026-06-29T08:00:00.000000Z",
        "delivered_at": null,
        "exception_at": null,
        "exception_reason": null
      }
    ]
  }
}
```

**Intentional omissions:** `shipping_address`, `billing_address` — encrypted, cannot verify ownership without patient session scope.

### `POST /api/v1/webhooks/prescribe-rx`

Receives order/encounter status events from PRX.

**Auth:** HMAC-SHA256 signature in `X-PRX-Signature` header, verified against `PRESCRIBE_RX_WEBHOOK_SECRET` env variable. In non-production with no secret configured, unsigned payloads are accepted. In production without a secret, all requests are rejected.

**Response:** `200 {"message": "Accepted."}` on success. `401` on invalid signature.

The handler is fully idempotent — re-delivering any event is safe.

---

## Checkout flow (summary — see `docs/checkout/dev.md` for full detail)

```
POST /api/v1/checkout
  → SubmitPrescribeRxCheckoutAction
      → PRX: submitUnifiedIntake()
      → DB transaction:
          → Encounter::create (prescribe_rx_encounter_id set)
          → Order::create (encounter_id set, prescribe_rx_order_id = NULL)
          → OrderItem::create × N (snapshotted from cart)
          → Lead: status = handed_off
          → Cart items: deleted
  → returns: order_uuid, checkout_path, PRX encounter data
```

The `prescribe_rx_order_id` on the order is blank until the first PRX webhook fires. `ReceivePrescribeRxWebhookAction::resolveOrder()` matches via `encounter → orders` on first delivery and backfills the IDs.

---

## Integration points

- **`Encounter`** — `Order.encounter_id` links to the clinical encounter. `Encounter.orders()` is a `HasMany`.
- **`Lead`** — linked via `Encounter.lead_id`. Not directly on Order.
- **`FulfillmentCenter`** — optional `Order.fulfillment_center_id` FK; exposed in `OrderResource` when loaded.
- **Patient portal** — `GET /api/v1/patient/orders` returns orders for an authenticated patient via the portal controller.

---

## Gotchas

- **`prescribe_rx_order_id` is nullable** — by design. PRX assigns the order ID asynchronously after checkout. The column was made nullable in migration `2026_06_28_234211`. Any code doing `where('prescribe_rx_order_id', ...)` must account for null rows.

- **Order items are encrypted at the name column** — `OrderItem.name` uses the `encrypted` Eloquent cast. Queries that filter or sort on `name` will not work at the database level.

- **Timestamp columns set once** — `syncOrder()` only sets `shipped_at`, `delivered_at`, `cancelled_at` when the field is currently null. Re-delivering a shipped event does not reset the timestamp.

- **`UpsertOrderAction` is not called by the webhook flow** — it exists for future use when PRX sends full order payloads. `ReceivePrescribeRxWebhookAction` handles all current webhook events directly.

- **No local payment path yet** — `CheckoutController` returns 503 for `checkout_path = local`. When NMI/AuthNet local checkout is wired, it will go through `PaymentGatewayManager` and create the Order directly rather than via PRX.
