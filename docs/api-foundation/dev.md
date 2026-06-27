# API Foundation — Developer Guide

**Module:** API Foundation (Phase A + C)
**Status:** Shipped 2026-06-22
**Relevant files:**

| Path | Purpose |
|---|---|
| `routes/api.php` | All `/api/v1/` route definitions |
| `config/api.php` | TTL, token ability sets |
| `config/sanctum.php` | Sanctum guard configuration |
| `app/Http/Controllers/Api/V1/ApiController.php` | Base controller — `success()` / `error()` helpers |
| `app/Http/Controllers/Api/V1/ConfigController.php` | `GET /api/v1/config` |
| `app/Http/Controllers/Api/V1/Auth/LoginController.php` | `POST /api/v1/auth/login` |
| `app/Http/Controllers/Api/V1/Auth/LogoutController.php` | `POST /api/v1/auth/logout` |
| `app/Http/Controllers/Api/V1/Auth/MeController.php` | `GET /api/v1/auth/me` |
| `app/Models/User.php` | `HasApiTokens` trait added |
| `app/Providers/AppServiceProvider.php` | Rate limiter registration |
| `tests/Feature/Api/V1/ConfigEndpointTest.php` | Config endpoint tests |
| `tests/Feature/Api/V1/Auth/AuthEndpointsTest.php` | Auth endpoint tests |

---

## Architecture

All API routes live under `/api/v1/` (versioned from day one). `bootstrap/app.php` registers `routes/api.php` as the `api` routing file — this was wired automatically by `php artisan install:api`.

### Response envelope

Every response from this API follows a consistent shape:

```json
// Success
{ "data": { ... }, "meta": { ... } }

// Error (also matches Laravel validation error shape)
{ "message": "...", "errors": { "field": ["msg"] } }
```

The `meta` key is optional — omitted when empty. All controllers extend `ApiController` and call `$this->success(array $data, array $meta = [], int $status)` or `$this->error(string $message, int $status)`.

### Authentication

Laravel Sanctum token auth. Three token scopes:

| Scope | Issued by | Used for |
|---|---|---|
| `frontend:*` | `POST /api/v1/auth/login` | React/Next.js authenticated users |
| `patient:*` | (future: patient portal flow) | Patient self-service |
| `integration:*` | Filament admin token manager (future) | 3rd-party CRM/webhook integrations |
| `admin:*` | Manual issuance | Server-to-server admin tooling |

Token abilities are documented in `config/api.php` under `token_abilities`.

### Rate limiting

Registered in `AppServiceProvider::configureRateLimiters()`:

| Limiter | Route group | Limit |
|---|---|---|
| `auth` | `/api/v1/auth/*` | 10 req/min per IP |
| `api` | All authenticated routes | 120 req/min per user (IP fallback) |

---

## Endpoints

### `GET /api/v1/config` — Public bootstrap

No authentication required. Cached for 5 minutes (`API_CONFIG_TTL` env var, default 300s).

**Purpose:** Single call for the React frontend on startup. Returns everything needed to render the app shell without additional requests.

**Response:**

```json
{
  "data": {
    "brand": {
      "name": "Acme Health",
      "tagline": "Your health, simplified.",
      "logo_url": "https://cdn.example.com/logo.png",
      "favicon_url": "https://cdn.example.com/favicon.ico",
      "hero_image_url": null,
      "announcement": {
        "emphasis": "New!",
        "text": "Free consultations this week."
      }
    },
    "theme": {
      "primary_color": "#2563eb",
      "accent_color": "#7c3aed",
      "accent_secondary_color": "#059669",
      "background_color": "#ffffff",
      "text_color": "#111827",
      "font_display": "Inter",
      "font_body": "Inter"
    },
    "contact": {
      "support_email": "support@example.com",
      "phone": "+1 555-000-0000",
      "social": {
        "instagram": "https://instagram.com/example"
      }
    },
    "seo": {
      "default_title": "Acme Health | Telehealth",
      "default_description": "...",
      "og_image_url": null,
      "allow_indexing": true
    },
    "provider": {
      "name": "PrescribeRx",
      "slug": "prescribe-rx",
      "supports_embed": true,
      "supports_patient_portal_auth": false
    }
  }
}
```

**Cache invalidation:** `cache()->forget('api.v1.config')` — add this call to Settings observer(s) once the Settings Filament pages ship.

### `POST /api/v1/auth/login`

Public. Rate-limited to 10/min per IP.

**Body:** `{ "email", "password", "device_name?" }`

**Returns:** `{ "data": { "token", "token_type": "Bearer", "user": { id, name, email } } }`

**Errors:**
- `422` — wrong credentials or validation failure
- `403` — account deactivated (`is_active = false`)

### `POST /api/v1/auth/logout`

Requires: `Authorization: Bearer {token}`

Revokes the **current token only**. Other tokens for the same user remain valid (useful when a user is logged in on multiple devices).

### `GET /api/v1/auth/me`

Requires: `Authorization: Bearer {token}`

Returns: `{ id, name, email, roles[], last_login_at }`

---

## Adding new endpoints

1. Create controller in `app/Http/Controllers/Api/V1/{Module}/`. Extend `ApiController`.
2. Register the route in `routes/api.php` inside the appropriate middleware group.
3. Add tests in `tests/Feature/Api/V1/{Module}/`.
4. Run `vendor/bin/pint --dirty --format agent`.

For modules that need token ability checks, use `$request->user()->tokenCan('patient:read')` or middleware `abilities:patient:read` (Sanctum middleware).

---

## Token ability middleware

Sanctum ships two middleware you can apply to routes:

```php
// Single ability required
Route::get('/prescriptions', ...)->middleware('ability:patient:read');

// Any one of these abilities
Route::get('/prescriptions', ...)->middleware('abilities:patient:read,frontend:*');
```

Register these in `bootstrap/app.php` under `->withMiddleware()` if needed:

```php
$middleware->alias([
    'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
    'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
]);
```
