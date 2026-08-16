# Leads Module — Developer Guide

**Status:** Shipped 2026-06-28

## Overview

The Leads module captures visitor intent at the start of a checkout flow. A lead record is created when the storefront submits the first checkout step (contact details + cart snapshot). The same record is updated as the visitor progresses through the prescribe-rx embed handoff and, ultimately, encounter completion.

Leads are the local tracking anchor for a checkout attempt. They carry enough state to:

- Pre-fill the checkout form on a return visit (recovery email → UUID lookup).
- Pass prefill data into the prescribe-rx embed (address, DOB, gender).
- Record cart contents and subtotal at capture time for abandonment analysis.
- Store UTM/referrer attribution for marketing reporting.
- Track PRX encounter/patient IDs and timestamps for reconciliation.

---

## Data Model

### `leads` table

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment. |
| `uuid` | uuid, unique | Public identifier. Route model binding key. Used by the frontend for prefill lookups. |
| `cart_ulid` | string(26), nullable | ULID of the anonymous cart session that created this lead. Used at checkout to prevent cross-session cart/lead substitution. Added 2026-06-27. |
| `patient_id` | FK → patients, nullable | Linked patient record once a patient exists. Added 2026-06-28. |
| `status` | string(32) | Cast to `LeadStatus` enum. Default: `new`. Indexed. |
| `first_name` | string | Required. |
| `last_name` | string | Required. |
| `email` | string | Required. Indexed. |
| `phone` | string, nullable | |
| `date_of_birth` | date, nullable | |
| `gender` | string(32), nullable | Free-form to match PRX flexibility (e.g. `male`, `female`, `other`). Used for PRX embed prefill. |
| `address_line1` | string, nullable | |
| `address_line2` | string, nullable | |
| `city` | string, nullable | |
| `state` | string(8), nullable | 2-letter code. |
| `postal_code` | string(16), nullable | |
| `country` | string(2) | Default `US`. |
| `sms_consent` | boolean | Default false. |
| `email_consent` | boolean | Default false. |
| `consent_given_at` | timestamp, nullable | Set automatically when either consent flag is true at creation time. |
| `cart_items` | json, nullable | Array of `CartItemData`-shaped objects. Snapshot locked at capture time. |
| `cart_subtotal` | decimal(10,2), nullable | Cart subtotal in USD at capture time. |
| `checkout_path` | string(32), nullable | Cast to `CheckoutPath` enum. `local` or `prx`. |
| `utm_source` | string, nullable | |
| `utm_medium` | string, nullable | |
| `utm_campaign` | string, nullable | |
| `utm_term` | string, nullable | |
| `utm_content` | string, nullable | |
| `referrer` | string(2048), nullable | HTTP referrer URL. |
| `landing_url` | string(2048), nullable | Landing page URL. |
| `user_agent` | string(512), nullable | Truncated to 512 chars. |
| `ip_address` | string(45), nullable | IPv4 or IPv6. |
| `prescribe_rx_encounter_id` | string, nullable | UUID of the PRX encounter. Indexed. Populated by `MarkLeadHandedOffAction`. |
| `prescribe_rx_patient_id` | string, nullable | UUID of the PRX patient. Indexed. Populated by `MarkLeadHandedOffAction`. |
| `handed_off_at` | timestamp, nullable | When the lead was handed off to PRX. |
| `completed_at` | timestamp, nullable | When the encounter was completed. |
| `prescribe_rx_response` | json, nullable | Last raw response payload from PRX for debugging. Populated by `MarkLeadCompletedAction`. |
| `notes` | text, nullable | Internal operator notes. |
| `created_at` / `updated_at` | timestamps | |
| `deleted_at` | timestamp, nullable | Soft delete. |

### Enums

**`App\Enums\LeadStatus`**

| Value | Label | Badge color |
|---|---|---|
| `new` | New | gray |
| `handed_off` | Handed off to PRX | warning (yellow) |
| `completed` | Completed | success (green) |
| `abandoned` | Abandoned | danger (red) |

**`App\Enums\CheckoutPath`**

| Value | Label |
|---|---|
| `local` | Local checkout (NMI / Authorize.net) |
| `prx` | PRX embed |

### `CartItemData` shape (stored in `cart_items` JSON)

```json
{
  "resource_type": "product",
  "resource_id": 42,
  "quantity": 1,
  "name": "Testosterone Cypionate",
  "unit_price": 149.00,
  "price_suffix": "/mo",
  "billing_period": "monthly",
  "prescribe_rx_id": "uuid-from-prx",
  "prescribe_rx_number": "PRX-001"
}
```

`resource_type` is one of `product`, `package`, or `plan`. The `prescribe_rx_id` and `prescribe_rx_number` fields map the local catalog item to its prescribe-rx counterpart for use at intake submission time.

### Relationships

```
leads -> hasMany -> encounters (App\Models\Commerce\Encounter)
leads -> belongsTo -> patients (App\Models\Patient, nullable)
```

---

## DTOs

### `App\Data\Leads\LeadData`

Input DTO used by `CreateLeadAction`. All fields mirror the `leads` table. `cart_items` is typed as `DataCollection<int, CartItemData>|array<int, CartItemData>`. `checkout_path` defaults to `CheckoutPath::PrescribeRx`.

### `App\Data\Leads\CartItemData`

Nested DTO for items inside `cart_items`. `resource_type` is validated to be one of `product`, `package`, `plan`. `quantity` minimum is 1.

---

## Actions

### `App\Actions\Leads\CreateLeadAction`

Creates a new `Lead` inside a database transaction. Accepts a `LeadData` DTO. Serializes `cart_items` from either a `DataCollection` or a plain array. Sets `status` to `LeadStatus::New_` unconditionally. Sets `consent_given_at` to now if either consent flag is true.

Used by `LeadController::store` (builds `LeadData` from the validated request + `X-Cart-Token` header) and available to admin-side Livewire flows.

### `App\Actions\Leads\MarkLeadHandedOffAction`

Transitions a lead to `LeadStatus::HandedOff`. Accepts the `Lead` model and optional `$encounterId` and `$patientId` strings. Sets `handed_off_at` to now. If IDs are not passed, the existing values on the model are preserved. Wrapped in a transaction.

### `App\Actions\Leads\MarkLeadCompletedAction`

Transitions a lead to `LeadStatus::Completed`. Accepts the `Lead` model and an optional `$response` array (raw PRX API response). Sets `completed_at` to now and stores the response in `prescribe_rx_response`. Returns a fresh model instance. Wrapped in a transaction.

---

## API Endpoints

Both endpoints are **unauthenticated** (public). No Sanctum token required.

### `POST /api/v1/leads`

Creates a new lead. Called by the frontend when the visitor submits the first checkout step.

**Request body (JSON):**

| Field | Type | Required | Notes |
|---|---|---|---|
| `first_name` | string | Yes | Max 100. |
| `last_name` | string | Yes | Max 100. |
| `email` | string | Yes | Valid email, max 255. |
| `phone` | string | No | Max 30. |
| `date_of_birth` | date | No | Must be at least 18 years ago. |
| `gender` | string | No | One of: `male`, `female`, `other`, `prefer_not_to_say`. |
| `address_line1` | string | No | Max 255. |
| `address_line2` | string | No | Max 255. |
| `city` | string | No | Max 100. |
| `state` | string | No | Max 8. |
| `postal_code` | string | No | Max 16. |
| `country` | string | No | ISO 3166-1 alpha-2, size 2. |
| `sms_consent` | boolean | No | Default false. |
| `email_consent` | boolean | No | Default false. |
| `checkout_path` | string | No | `local` or `prx`. |
| `cart_items` | array | No | Array of cart item objects. |
| `cart_subtotal` | numeric | No | Min 0. |
| `utm_source` | string | No | Max 255. |
| `utm_medium` | string | No | Max 255. |
| `utm_campaign` | string | No | Max 255. |
| `utm_term` | string | No | Max 255. |
| `utm_content` | string | No | Max 255. |
| `referrer` | url | No | Max 2048. |
| `landing_url` | url | No | Max 2048. |

**Headers:**

| Header | Notes |
|---|---|
| `X-Cart-Token` | Optional ULID of the anonymous cart session. Stored as `cart_ulid` to prevent cross-session pairings at checkout. |

The controller also captures `ip_address` from the request and `user_agent` from the `User-Agent` header automatically — do not pass these in the body.

**Response: 201 Created**

```json
{
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "new",
    "first_name": "Jane",
    "last_name": "Doe",
    "email": "jane@example.com",
    "phone": null,
    "date_of_birth": null,
    "gender": null,
    "address": {
      "line1": null,
      "line2": null,
      "city": null,
      "state": null,
      "postal_code": null,
      "country": "US"
    },
    "consents": {
      "sms": false,
      "email": false,
      "given_at": null
    },
    "checkout_path": "prx",
    "handoff_url": "https://example-install.com/checkout/handoff/550e8400-e29b-41d4-a716-446655440000",
    "cart_items": [],
    "cart_subtotal": null,
    "handed_off_at": null,
    "completed_at": null
  }
}
```

`handoff_url` is the absolute URL of the server-rendered Prescribe-Rx embed handoff page. When the configured checkout path is `prx`, the frontend redirects the browser here immediately after lead creation — see `docs/checkout/dev.md`. The URL host follows the request host (the API base the frontend called), so it is correct in every environment without configuration.

**Response: 422 Unprocessable Entity** — standard Laravel validation error envelope.

---

### `GET /api/v1/leads/{uuid}`

Retrieves a lead by its UUID. The UUID acts as a bearer credential — the frontend uses it from a recovery email link to pre-fill the checkout form.

**Route binding:** `{lead}` is resolved by `uuid` column (`getRouteKeyName` returns `'uuid'`).

**Response: 200 OK** — same shape as the `POST` response body.

**Response: 404 Not Found** — if no lead exists with that UUID.

**Security note:** This endpoint is unauthenticated. The UUID is the only access control. Do not expose UUIDs in publicly enumerable locations (e.g., sitemaps, public logs). Share only in recovery email links addressed to the lead owner.

---

## Filament Admin

The Filament resource lives at `App\Filament\Resources\Leads\LeadResource`. Navigation group: **Leads**, sort order 10.

- **No create page.** Leads are created by the public API only. `ListLeads::getHeaderActions()` returns an empty array.
- **Edit page** supports soft delete (`DeleteAction`), force delete (`ForceDeleteAction`), and restore (`RestoreAction`).
- **Bulk actions** on the list: soft delete, force delete, restore.
- Route model binding uses `getRecordRouteBindingEloquentQuery` with `SoftDeletingScope` removed, so soft-deleted records can still be accessed directly via URL.

The `LeadResource::$recordTitleAttribute` is `email`, so the breadcrumb and page title show the lead's email address.

---

## Integration Points

### prescribe-rx Handoff

When the checkout flow hands a lead off to the PRX embed, call `MarkLeadHandedOffAction::execute($lead, $encounterId, $patientId)`. This populates `prescribe_rx_encounter_id`, `prescribe_rx_patient_id`, and `handed_off_at`, and sets status to `handed_off`.

When the PRX embed completes and a webhook or confirmation callback is received, call `MarkLeadCompletedAction::execute($lead, $responsePayload)`. This sets status to `completed`, stamps `completed_at`, and stores the raw response for debugging.

### Cart Module

The `cart_ulid` column links a lead to the anonymous cart that was active when the lead was created. At checkout, verify that the lead's `cart_ulid` matches the `X-Cart-Token` header to prevent a visitor from substituting a different cart for another visitor's lead.

### Encounters

`Lead::encounters()` returns the `hasMany` relationship to `App\Models\Commerce\Encounter`. Encounters are the clinical records created downstream in prescribe-rx and synced locally.

### Patients

`leads.patient_id` is a nullable FK to `patients`. It is populated once a patient record is matched or created after PRX handoff. Nulls on delete.

---

## Gotchas and Design Decisions

### UUID as route key and access credential

The UUID is both the route key for model binding and the only credential guarding the `GET /api/v1/leads/{uuid}` endpoint. There is no authentication on this endpoint by design — recovery email links must work without requiring the visitor to log in. Keep UUIDs out of server logs that are accessible to unauthorized parties.

### date_of_birth minimum age validation

The `store` endpoint validates `date_of_birth` as `before:-18 years`, enforcing that the lead is at least 18 years old. This is a telehealth compliance requirement.

### gender is free-form in the database

The `gender` column accepts any string up to 32 chars. The API validates it against a fixed set (`male`, `female`, `other`, `prefer_not_to_say`), but the column was designed this way to stay flexible for PRX embed prefill, which may accept alternative values in the future.

### Cart snapshot is immutable after capture

`cart_items` and `cart_subtotal` are written once at lead creation and are never updated by the system. They reflect what the visitor selected at the moment they submitted the first checkout step, even if the live catalog changes afterward.

### Soft deletes on admin list

The `TrashedFilter` in `LeadsTable` lets operators see trashed records. The route model binding query removes the soft-delete scope so trashed leads remain accessible by UUID from the edit URL. This is intentional — operators need to be able to view and restore leads that were accidentally deleted.
