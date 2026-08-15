# Checkout Module — Developer Guide

**Status:** Complete (PRX path + local gateway path)

---

## Overview

Checkout bridges a visitor's cart + lead to the payment/clinical provider. A single toggle in `BillingSettings` controls which path is active:

| `checkout_path` | Provider | Who charges the card |
|---|---|---|
| `prx` *(default)* | Prescribe-Rx embed | PRX collects payment + intake inside its hosted embed |
| `local` | Configured merchant account | This app charges via NMI / Auth.Net / Stripe / Square |

Both paths return the same `CheckoutResultData` shape. The frontend branches on `checkout_path` to decide the next step.

---

## API endpoints

### `GET /api/v1/checkout/gateway-config`

**Auth:** Unauthenticated (rate-limited). Called on page load to initialise the client-side tokenization SDK.

| Gateway | SDK | Key field |
|---|---|---|
| `nmi` | Collect.js | `public_key` |
| `authorize_net` | Accept.js | `public_key` |
| `stripe` | Stripe.js | `public_key` |
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

**Response `503`:** No active default merchant account configured.

---

### `POST /api/v1/checkout`

**Auth:** Unauthenticated (rate-limited to 20 req/min).

**Request body:**

| Field | Type | Required | Description |
|---|---|---|---|
| `cart_ulid` | string | Yes | ULID of the cart to check out |
| `lead_uuid` | string | Yes | UUID of the lead created at checkout start |
| `intake_answers` | object | No | Pre-filled answers for PRX intake. Ignored on local path. |
| `payment_method` | object | Local only | Tokenized payment data from the gateway SDK (see below) |

**`payment_method` shape by gateway:**

| Gateway | Required keys |
|---|---|
| NMI | `{ "payment_token": "..." }` |
| Authorize.Net | `{ "dataDescriptor": "...", "dataValue": "..." }` |
| Stripe | `{ "payment_method_id": "pm_..." }` |
| Square | `{ "nonce": "..." }` |

**Session pairing check:** If `leads.cart_ulid` is set, it must match `cart_ulid` via `hash_equals()`. Returns `403` on mismatch.

**Response `201` — PRX path:**
```json
{
  "data": {
    "order_uuid": "...",
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

**Response `201` — local path:**
```json
{
  "data": {
    "order_uuid": "...",
    "checkout_path": "local",
    "prescribe_rx": null
  }
}
```

**Error responses:**

| Status | Cause |
|---|---|
| 403 | Cart/lead session mismatch |
| 422 | Missing `payment_method` on local path; payment declined; empty cart; PRX rejection |
| 503 | Unhandled exception |

---

## Routing logic

```
BillingSettings.checkout_path
  'local'  →  validate payment_method present  →  SubmitLocalCheckoutAction
  'prx'    →  SubmitPrescribeRxCheckoutAction
```

Both actions return `CheckoutResultData`.

---

## Action: `SubmitLocalCheckoutAction`

### Flow

1. Verify cart is not empty
2. Resolve default active `MerchantAccount` via `PaymentGatewayManager`
3. **Charge the gateway outside the DB transaction** — a rollback cannot reverse a captured payment
4. Throw `RuntimeException` if `PaymentResult::success === false` (controller returns 422)
5. Inside `DB::transaction`:
   - `Order::create` — subtotal/total from `cart->subtotal()`, no `encounter_id`
   - `OrderItem::create × N` — snapshotted from cart items
   - Store in `order.metadata`: `gateway_transaction_id`, `merchant_account_id`, `gateway_provider`, `lead_uuid`
   - `Lead::update` → status `completed`
   - Delete cart items (cart record preserved for analytics)
6. Return `CheckoutResultData { order_uuid, checkout_path: 'local' }`

---

## Action: `SubmitPrescribeRxCheckoutAction`

### Product ID resolution

Priority per cart item:
1. **Package + Plan** → `Plan.provider_product_ids` (JSON array)
2. **Bare Plan itemable** → `Plan.provider_product_ids`
3. **Product itemable** → `Product.provider_product_id`
4. **Package (no plan)** → each `Package.products[*].provider_product_id`

Duplicates stripped; empty/null filtered. Throws if none resolved (422).

### Transaction boundary

The PRX HTTP call is made **outside** the DB transaction. The transaction wraps only local DB writes:

```
DB::transaction:
  Encounter::create     ← prescribe_rx_encounter_id from PRX response
  Order::create         ← encounter_id set; prescribe_rx_order_id = null (backfilled by webhook)
  OrderItem::create × N
  Lead::update          ← status = handed_off, PRX IDs stored
  Cart items: delete
```

---

## BillingSettings

**Class:** `App\Settings\BillingSettings` | **Group:** `billing`

| Property | Default | Values |
|---|---|---|
| `checkout_path` | `'prx'` | `'prx'` or `'local'` |

**Admin:** Settings → Billing (radio with descriptions).

---

## DTOs

| Class | Purpose |
|---|---|
| `CheckoutData` | Input — `cart_ulid`, `lead_uuid`, `intake_answers`, `payment_method?` |
| `CheckoutResultData` | Output — `order_uuid`, `checkout_path`, `prescribe_rx?` |

---

## Files

```
app/
├── Actions/Checkout/
│   ├── SubmitPrescribeRxCheckoutAction.php
│   └── SubmitLocalCheckoutAction.php
├── Actions/Settings/UpdateBillingSettingsAction.php
├── Data/Checkout/CheckoutData.php
├── Data/Checkout/CheckoutResultData.php
├── Data/Settings/BillingSettingsData.php
├── Filament/Pages/Settings/ManageBilling.php
├── Http/Controllers/Api/V1/Checkout/CheckoutController.php
└── Settings/BillingSettings.php

database/settings/2026_06_29_020250_create_billing_settings_migration.php

tests/Feature/
├── Api/V1/Checkout/CheckoutControllerTest.php  (PRX path — 8 tests)
└── Checkout/LocalCheckoutTest.php              (local path — 8 tests)
```

---

## Gotchas

- **`intake_answers` is almost always empty at checkout** — PRX embed collects intake after handoff. It's a pass-through for future pre-fill scenarios.
- **`payment_method` is gateway-specific** — the frontend must use the gateway SDK matching `GET /checkout/gateway-config` response to tokenize before submitting. Never send raw card numbers.
- **`prescribe_rx_encounter_type_id` must be set** in Integration Settings before PRX checkout works.
- **Local path: gateway is charged before DB writes** — if the transaction fails after a successful charge, the order is missing but the payment exists. Recover by checking the gateway dashboard and manually creating the order.
