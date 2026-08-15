# prx-backend

**A brand-agnostic Laravel API backend + Filament admin panel for telemedicine e-commerce.**

Each client deploys their own instance — fresh install, fresh database, fully configured through the admin UI. Nothing client-specific (branding, copy, credentials, tracking, theme) lives in code. Every deployment drives a **decoupled JavaScript frontend** (Next.js/React or any stack) through a versioned REST API, and integrates with the **prescribe-rx** clinical platform for encounters, prescriptions, and fulfillment.

```
React / Next.js frontend (separate repo, separate origin)
        │  HTTP  →  /api/v1/*  (optional ApiClient bearer token)
        ▼
   API Controller  →  validates request
        ▼
   DTO (spatie/laravel-data)
        ▼
   Action (single-purpose, owns the DB transaction)
        │  ↘ Service (business logic, external integrations)
        ▼
   API Resource  →  JSON envelope { data, meta?, message? }
```

The Filament admin follows the same backend layers — admin UI dispatches a DTO to an Action instead of an API controller doing it. Full architecture: [`docs/architecture/dev.md`](docs/architecture/dev.md).

## Stack

| Layer | Choice |
|---|---|
| Framework | PHP 8.5 · Laravel 13 |
| Admin panel | Filament 4 + filament-shield (Spatie permissions) · Livewire 3 (admin only — no public Livewire) |
| Data layer | spatie/laravel-data (DTOs) · spatie/laravel-settings (typed, DB-driven config) |
| API auth | Laravel Sanctum (ApiClient bearer tokens + origin pinning) |
| Media | awcodes/curator media library |
| Queues | Redis + Laravel Horizon |
| API docs | dedoc/scramble → live Scalar UI at `/api/docs` |
| Frontend tooling | Vite 5 · Tailwind CSS v3 (admin theme only) |

## Quick start (fresh install)

Requirements: PHP 8.5, Composer, MySQL, Redis, Node 20+.

```bash
git clone <this-repo> && cd prx-backend
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
# .env: set DB_*, REDIS_*, APP_URL — and the first admin:
#   ADMIN_NAME=…  ADMIN_EMAIL=…  ADMIN_PASSWORD=…   (never committed)
php artisan storage:link
php artisan migrate --seed        # runs AdminUserSeeder + BaseRolesSeeder
php artisan serve
```

Log in at `/admin` with the `ADMIN_*` credentials. The seeders create a `super_admin` (Gate-level bypass) plus a default role matrix (`admin`, `content_editor`, `catalog_manager`, `support`) you can tune in the Roles UI.

Optional development content:

```bash
php artisan db:seed --class=DevCatalogSeeder   # neutral demo products/packages
php artisan db:seed --class=HomePageSeeder     # placeholder home page (13 sections)
```

After any schema/config reset run `php artisan optimize:clear` (not just `cache:clear`).

## White-labeling: everything is admin-configured

All install-specific values are DB-driven via `Settings` pages in the admin and served to the frontend in one bootstrap call, `GET /api/v1/config`:

- **Brand** — name, tagline, logos, favicon, announcement (also rebrands the admin panel itself and outbound mail)
- **Theme** — colors, fonts, per-install custom CSS, frontend template selector
- **Contact** — emails, phone, address, hours, social links
- **SEO & Analytics** — meta defaults, GA4 / GTM / Meta / TikTok pixels, raw custom tracking scripts, indexing toggle
- **Billing / Integrations** — checkout path (prescribe-rx embed vs. local gateway), merchant accounts, prescribe-rx credentials — all encrypted at rest

## API

Everything public is under `/api/v1` with a `{ data, meta?, message? }` envelope. Content reads (config, pages, layout, menus, catalog, blog, FAQ, profiles) are public; issue an **ApiClient token** in the admin to pin production frontends to allowed origins. Live reference: **`/api/docs`**.

Building a frontend against this backend? Start at [`docs/frontend/dev.md`](docs/frontend/dev.md) (implementer guide) and [`docs/frontend/user.md`](docs/frontend/user.md) (operator checklist). The frontend runs on its **own origin** — it is not a path under this app.

## Modules

| Module | Status | Docs |
|---|---|---|
| Auth, users, roles (Shield) | ✅ | [`docs/api-foundation`](docs/api-foundation) |
| Settings (brand/theme/contact/SEO/integrations) | ✅ | [`docs/settings`](docs/settings) |
| CMS page builder (22 blueprints, flexible types, menus, regions, revisions) | ✅ | [`docs/page-builder`](docs/page-builder), [`docs/cms`](docs/cms) |
| Catalog (products, packages, plans, provider mapping) | ✅ | [`docs/catalog`](docs/catalog) |
| Blog | ✅ | [`docs/blog`](docs/blog) |
| Cart + Checkout (prx embed / local gateways) | ✅ | [`docs/cart`](docs/cart), [`docs/checkout`](docs/checkout) |
| Orders + prescribe-rx webhook sync | ✅ | [`docs/orders`](docs/orders) |
| Leads | ✅ | [`docs/leads`](docs/leads) |
| Payments / merchant accounts (NMI, Authorize.net, Stripe, Square) | ✅ | [`docs/payments`](docs/payments) |
| Intake schema + prescribe-rx integration | ✅ | [`docs/intake`](docs/intake), [`docs/prescribe-rx`](docs/prescribe-rx) |
| API clients & tokens | ✅ | [`docs/api-clients`](docs/api-clients) |
| Clinical decision trees · Bedrock LLM protocol suggester | 🔲 deferred | — |

Full index with one-line descriptions: [`docs/README.md`](docs/README.md).

## Development

```bash
php artisan test --compact            # PHPUnit (feature tests by default)
vendor/bin/pint --dirty               # code style — run before committing
php artisan shield:generate --all --panel=admin --no-interaction   # after adding Filament resources/pages
```

Conventions that are non-negotiable: controllers and Livewire never touch the DB directly (DTO → Action → Service), Actions own transactions, integrations live in `app/Services`, credentials use `encrypted:*` casts, and no client-specific value ever appears in code. See [`CLAUDE.md`](CLAUDE.md) and [`docs/architecture/dev.md`](docs/architecture/dev.md).

## Production notes

Multiple Laravel apps can share one Redis instance — all keys are namespaced from `APP_NAME` (`REDIS_PREFIX`, `CACHE_PREFIX`, `HORIZON_PREFIX`, session cookie). Run `php artisan horizon` under Supervisor; Reverb is installed for future broadcasting. Cache only resolved arrays (never Eloquent models) — serialized models break across PHP runtimes sharing Redis.
