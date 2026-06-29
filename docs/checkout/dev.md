# Checkout Module — Developer Guide

**Status:** Shipped 2026-06-28 (PRX path only)

---

## Overview

Checkout is the bridge between a visitor's cart + lead and the clinical/payment provider. The single `POST /api/v1/checkout` endpoint accepts a cart ULID and a lead UUID, submits the intake to PrescribeRx, and returns the order UUID + PRX encounter context for the frontend to load the embed iframe.

The `local` checkout path (NMI/AuthNet direct) is stubbed — the controller returns 503 until `PaymentGatewayManager` is wired in.

---

## API endpoints

### `GET /api/v1/checkout/gateway-config`

**Auth:** Unauthenticated (rate-limited). Called by the frontend before rendering the payment form.

Returns the active merchant account's gateway type, public key, and environment so the frontend can load the correct client-side tokenization SDK:

| Gateway | SDK to load | Key field |
|---|---|---|
| `nmi` | Collect.js | `public_key` |
| `authorize_net` | Accept.js | `public_key` |
| `stripe` | Stripe.js | `public_key` (`pk_live_...` or `pk_test_...`) |
| `square` | Square Web Payments SDK | `public_key` (application_id) + `location_id` |

**Response `200`:**
```json
{
  "data": {
    "gateway_provider": "stripe",
    "environment": "sandbox",
    "public_key": "pk_test_abc123..."
  }
}
```

For Square, a `location_id` field is also included when set on the merchant account.

**Response `503`:** No active default merchant account configured.

---

### `POST /api/v1/checkout`

**Auth:** Unauthenticated (rate-limited to 20 req/min).

**Request body:**

| Field | Type | Required | Description |
|---|---|---|---|
| `cart_ulid` | string | Yes | ULID of the cart to check out. |
| `lead_uuid` | string | Yes | UUID of the lead created at checkout start. |
| `intake_answers` | object | No | Pre-filled intake answers keyed by PRX field slug. Typically empty — the PRX embed collects clinical answers after handoff. |

**Session pairing check:** If `leads.cart_ulid` is set, it must match `cart_ulid` via `hash_equals()`. This prevents substituting a different cart or lead mid-session. Returns `403` on mismatch.

**Response `201`:**
```json
{
  "data": {
    "order_uuid": "01hxx...",
    "checkout_path": "prx",
    "prescribe_rx": {
      "encounter_id": "prx-encounter-uuid",
      "encounter_number": "ENC-12345",
      "patient_id": "prx-patient-uuid",
      "status": "pending_intake"
    }
  }
}
```

The frontend uses `checkout_path` to determine the next step:
- `"prx"` → load the PRX embed iframe using `encounter_id`
- `"local"` → render local payment form (not yet implemented — returns 503)

**Error responses:**
- `403` — cart/lead session mismatch
- `422` — runtime error from PRX (e.g. missing product IDs, patient validation failure)
- `503` — unhandled exception; checkout could not be completed

---

## Action: `SubmitPrescribeRxCheckoutAction`

### Product ID resolution

Cart items map to PRX product IDs following this priority per item:

1. **Package + Plan selected** → `Plan.provider_product_ids` (JSON array)
2. **Bare Plan itemable** → `Plan.provider_product_ids`
3. **Product itemable** → `Product.provider_product_id`
4. **Package itemable (no plan)** → iterates `Package.products`, collects each `provider_product_id`

Duplicates are stripped. Empty/null IDs are filtered. If no IDs are resolved, a `RuntimeException` is thrown (returns 422).

### Encounter type resolution

`encounter_type_id` sent to PRX comes from `IntegrationSettings::prescribe_rx_encounter_type_id` — a single universal encounter type UUID configured per deployment. All products, packages, and plans route to this one type.

Intake question visibility is handled dynamically on the frontend: the React form fetches the full schema via `GET /api/v1/intake/schema`, then hides steps that are already filled from the lead or not applicable to the products in the cart. The `intake_answers` payload at checkout captures only the questions that were shown and answered.

### Transaction boundary

The PRX HTTP call is made **outside** the DB transaction intentionally — a failed transaction would not be able to roll back the PRX-side encounter creation. The transaction wraps only local DB writes:

```
DB::transaction:
  Encounter::create   ← prescribe_rx_encounter_id from PRX response
  Order::create       ← encounter_id set; prescribe_rx_order_id = null (backfilled by webhook)
  OrderItem::create × N  ← snapshotted from cart
  Lead::update        ← status = handed_off, PRX IDs stored
  Cart items: delete  ← cart cleared; cart record preserved for analytics
```

---

## DTOs

### `App\Data\Checkout\CheckoutData`
Input DTO. `cart_ulid`, `lead_uuid`, `intake_answers` (array, default `[]`).

### `App\Data\Checkout\CheckoutResultData`
Output DTO returned by `SubmitPrescribeRxCheckoutAction::execute()`. Fields: `order_uuid`, `checkout_path`, `prescribe_rx` (array with `encounter_id`, `encounter_number`, `patient_id`, `status`).

---

## Integration points

- **Cart** — loaded by ULID; items eager-loaded with `itemable` and `plan`. Deleted after checkout.
- **Lead** — loaded by UUID; status updated to `HandedOff`; PRX encounter/patient IDs stored.
- **Catalog** — `Product`, `Package`, `Plan` provide `provider_product_id` / `provider_product_ids` for PRX submission.
- **PrescribeRx Client** — `Client::submitUnifiedIntake(UnifiedIntakeRequestData)` makes the HTTP call. See `docs/prescribe-rx/dev.md`.
- **IntegrationSettings** — provides `prescribe_rx_sales_org_id`, `prescribe_rx_client_id`, `prescribe_rx_environment`.

---

## Gotchas

- **`intake_answers` is almost always empty at checkout** — the PRX embed collects the clinical intake after handoff. `intake_answers` is a pass-through for future pre-fill scenarios (e.g. the patient already answered questions in a local wizard before hitting checkout).

- **Cart items are deleted even if the PRX call succeeds but the DB transaction fails** — the PRX call is outside the transaction. If the transaction rolls back, the encounter exists in PRX but no local Order/Encounter records exist. This is recoverable via the incoming webhook that PRX fires.

- **No local payment path** — `BillingSettings.checkout_path = 'local'` is not yet implemented. Attempting it returns 503.

- **`prescribe_rx_encounter_type_id` must be set** in Integration Settings before checkout works. If null, the PRX API receives a null encounter type which may be rejected. Configure it from the Filament admin after linking the sales org in the PRX admin.
