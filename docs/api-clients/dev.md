# API Clients — Developer Guide

Status: complete

## Overview

`ApiClient` represents a registered frontend or integration that consumes the `/api/v1/` API. Each client gets one or more Sanctum bearer tokens scoped to a defined ability tier. The `VerifyApiClientOrigin` middleware enforces origin pinning per client.

This is separate from **patient tokens** (`App\Models\Patient`) and **admin-user session auth** (`App\Models\User`). All three token types coexist in `personal_access_tokens` via Sanctum's polymorphic `tokenable_type` / `tokenable_id` columns.

---

## Data model

**Table:** `api_clients`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned | Internal PK |
| `uuid` | char(36) unique | Route key; use this in URLs |
| `name` | varchar(255) | Human-readable label |
| `description` | text nullable | Optional notes |
| `allowed_origins` | json nullable | Array of allowed CORS origins. `null` = any origin. |
| `default_abilities` | json | Ability array pre-filled when issuing a new token. |
| `is_active` | boolean | Inactive clients are rejected at the middleware level. |
| `created_at` / `updated_at` | timestamps | — |

**Model:** `App\Models\ApiClient`  
Uses `HasApiTokens` (from Sanctum), `HasUuids`, `HasFactory`.

---

## Token abilities

Defined in `App\Enums\TokenAbility`:

| Case | Value | Access |
|---|---|---|
| `PublicRead` | `public:read` | Catalog, CMS pages, blog (read-only public content) |
| `Checkout` | `checkout:*` | Leads, cart, checkout submission |
| `PatientPortal` | `patient:*` | Authenticated patient-portal routes |
| `AdminApi` | `admin:*` | Server-to-server admin endpoints |

> **Note:** Ability enforcement on individual routes is not yet wired with `->middleware('abilities:...')`. The current implementation records abilities in the token record and is ready for future per-route enforcement.

---

## Issuing tokens

**Action:** `App\Actions\ApiClients\IssueApiClientTokenAction`

```php
$plainText = app(IssueApiClientTokenAction::class)
    ->execute($client, 'Production Next.js', [TokenAbility::PublicRead->value]);
```

- Throws `RuntimeException` if the client is inactive.
- Falls back to `$client->default_abilities` when no `$abilities` array is passed.
- Returns the **plain-text token** — only available at creation time. Store it securely; it is not recoverable from the database.

**Filament admin:** open any `ApiClient` record → **"Issued tokens"** relation panel → **"Issue token"** button. The plain-text token is shown in a persistent notification immediately after generation.

---

## Origin verification middleware

**Class:** `App\Http\Middleware\VerifyApiClientOrigin`  
**Registered:** appended to the `api` middleware group in `bootstrap/app.php` — runs on all `/api/*` requests automatically.

Logic:
1. No `Authorization: Bearer` header → pass through (public, no-token request).
2. Token found but `tokenable_type != ApiClient` → pass through (patient or user token, not our concern).
3. `ApiClient.is_active === false` → `403 API client is inactive.`
4. `ApiClient.allowed_origins` is empty or null → pass through (any origin allowed).
5. `Origin` header absent or not in `allowed_origins` → `403 Origin not allowed.`

> The middleware calls `PersonalAccessToken::findToken()` on every request that carries a bearer token. Sanctum's own auth guard also calls this for `auth:sanctum` routes, resulting in one extra DB read on authenticated routes. This is acceptable given the simplicity; add a request-level cache here if profiling shows it as a hot path.

---

## Filament admin

Located in `Users & access` nav group.

- **List:** shows name, token count, origin restrictions, active status.
- **Create / Edit:** set name, description, allowed origins (TagsInput), default abilities (CheckboxList), active toggle.
- **View → Issued tokens panel:** lists all issued tokens with their abilities and last-used timestamp. Use **"Revoke"** to permanently delete a token.
- **Issue token:** button in the tokens panel opens a modal to set a token name and ability overrides, then displays the plain-text token in a persistent notification.

After adding or editing a resource, remember to run:
```bash
php artisan shield:generate --all --panel=admin --no-interaction
php artisan shield:super-admin --user=1 --panel=admin --no-interaction
```

---

## Files

```
app/
├── Enums/TokenAbility.php
├── Models/ApiClient.php
├── Actions/ApiClients/IssueApiClientTokenAction.php
├── Http/Middleware/VerifyApiClientOrigin.php
└── Filament/Resources/ApiClients/
    ├── ApiClientResource.php
    ├── Schemas/ApiClientForm.php
    ├── Tables/ApiClientsTable.php
    ├── RelationManagers/TokensRelationManager.php
    └── Pages/ (List, Create, View, Edit)

database/
├── migrations/2026_06_29_012406_create_api_clients_table.php
└── factories/ApiClientFactory.php

tests/Feature/ApiClients/
├── ApiClientTokenTest.php
└── VerifyApiClientOriginMiddlewareTest.php
```
