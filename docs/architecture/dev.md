# Architecture — Developer Reference

Status: current as of 2026-08-15. This is the canonical current-state architecture document. (`IMPLEMENTATION_PLAN.md` in this directory is the original build plan, kept for history — where they disagree, this file wins.)

## What this system is

A reusable, brand-agnostic foundation for telemedicine e-commerce. One codebase, many deployments: each client gets their own instance (own server/database/Redis DBs), configures everything through the Filament admin, and points their own frontend at the API. The repo must therefore never contain a client name, logo, credential, tracking ID, or copy block — those are data, not code.

Three surfaces per deployment:

1. **`/api/v1` REST API** — everything the public frontend needs (content, catalog, cart, checkout, leads, patient portal). Versioned; breaking changes bump to `/v2`.
2. **Filament admin at `/admin`** — operators manage content, catalog, orders, settings, users, API tokens.
3. **prescribe-rx integration** — the clinical system of record (encounters, prescriptions, fulfillment). Checkout either hands off to its embed or charges locally through a configured gateway.

## Layering (non-negotiable)

```
HTTP (API controller)  or  Filament/Livewire (admin)
        ▼ validate, build typed DTO ({Entity}Data — spatie/laravel-data)
     Action             single-purpose; owns DB::transaction via Transacts::tx()
        ▼ ↘ Service     business logic + external APIs (app/Services/{Module})
     Response           API Resource (JSON envelope) or DTO → Filament toast
```

- Controllers and Livewire components **never** touch the database directly.
- Actions throw on failure; the transaction rolls back. API errors return `{ message, errors? }`; admin errors surface as Filament notifications.
- No raw arrays across layer boundaries — DTOs in, Resources out.

## Directory map

```
app/
├── Http/Controllers/Api/V1/{Module}/   # thin: validate → DTO → Action → Resource
├── Http/Resources/Api/V1/{Module}/     # response shapes
├── Actions/{Module}/                   # CreateXAction … extend BaseAction, use Transacts
├── Services/{Module}/                  # PrescribeRx client, Payments gateways, Cms services
├── Data/{Module}/                      # DTOs
├── Models/{Module?}/                   # encrypted:* casts for credentials
├── Policies/{Module?}/                 # mirrors Models subnamespaces (auto-discovered)
├── Contracts/{Module}/                 # e.g. Payments\PaymentGatewayInterface
├── Enums/                              # backed enums, cast on models
├── Filament/                           # panel resources, settings pages, widgets
└── Settings/                           # spatie/laravel-settings typed groups
```

## Authorization model

- Panel access requires an **active user with ≥1 role** (`User::canAccessPanel`).
- Permissions are Shield-generated `Verb:Model` strings; policies mirror model namespaces and are auto-discovered.
- `super_admin` is a **`Gate::before` bypass** (`config/filament-shield.php` → `define_via_gate: true`) — it never depends on permission sync.
- Default role matrix seeded by `BaseRolesSeeder`; first admin by `AdminUserSeeder` from `ADMIN_*` env.
- Enforcement rules: custom Filament Pages need `HasPageShield`, widgets need `HasWidgetShield`, and every custom `Action::make()` that writes needs an explicit `->visible(... ->can('Verb:Model'))` — only stock CRUD actions consult policies automatically.

## Settings & white-label model

Typed settings groups (Brand, Theme, Contact, Seo, Integration, Billing, Llm, Communication) stored in DB, edited via Filament, exposed to the frontend through the cached `GET /api/v1/config` bootstrap payload. The admin panel itself and outbound mail also read BrandSettings (rescue-guarded — the settings table doesn't exist mid-install). Per-install API credentials live on models/settings with `encrypted:*` casts, never in `.env`.

## CMS / page builder

Hybrid registry: 22 code blueprints (`app/Cms/Sections`, `SectionType` enum) + admin-defined flexible types, unified behind `SectionRegistry`. The API emits a **section envelope** — `{type, origin: code|flexible, anchor, global, data, schema?}` — where `schema` (a field-kind map) lets frontends render flexible types generically. Six fixed layout regions, slug-addressed menus with morph entity links (`{type, slug}` — frontend owns route patterns), pre-edit revisions, Curator media, DOM-allowlist SVG sanitization. Details: `docs/page-builder/dev.md`.

**Caching rule:** `CmsCache` uses a versioned namespace bumped by observers. Cache **resolved arrays only, never Eloquent models** — serialized models break with "incomplete class" errors when a second PHP runtime (FPM vs `artisan serve`) shares the same Redis.

## Integrations

| Integration | Service | Credentials |
|---|---|---|
| prescribe-rx (clinical API) | `App\Services\PrescribeRx\Client` | `IntegrationSettings`, encrypted |
| NMI / Authorize.net / Stripe / Square | `App\Services\Payments\Gateways\*` behind `PaymentGatewayInterface`; per-install selection via `MerchantAccount` | encrypted model casts |
| AWS Bedrock (deferred) | `App\Services\Llm\*` | encrypted |

Checkout path (prx embed vs. local gateway) is a `BillingSettings` toggle, not a code branch. Local `Product`/`Package` rows map to remote inventory via `provider_*` fields — marketing catalog is decoupled from clinical inventory.

## Frontend contract

The frontend is a separate app on its own origin. Contract, theming model, datasets, and commerce flow: `docs/frontend/dev.md`. Public reads need no token; production frontends use ApiClient bearer tokens with origin pinning (`VerifyApiClientOrigin` middleware — enforced only when a token is presented).

## Deployment

Per-install: fresh DB, `migrate --seed`, operator sets Settings, issues an ApiClient token, authors content. Multi-app servers share Redis via `APP_NAME`-derived prefixes (redis/cache/horizon/session); Horizon under Supervisor; Reverb reserved for future broadcasting. OpenAPI is generated from code (Scramble) at `/api/docs` — annotate new routes so they're picked up.
