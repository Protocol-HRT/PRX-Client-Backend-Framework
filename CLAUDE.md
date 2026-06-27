# prx-backend — Project Spec

**Read this file before doing anything. The architecture rules below are non-negotiable.**

This top section is hand-maintained. The Boost-managed block at the bottom (delimited by `laravel-boost-guidelines` tags) is rewritten by `php artisan boost:update` — do not edit it directly, and avoid mentioning that tag name inline in this hand-maintained section (Boost's regex replacer is greedy and will eat the whole file from the first match).

## What this is

A **brand-agnostic Laravel API backend + Filament admin panel** for telemedicine e-commerce. Built as a reusable foundation that each client deploys as their own instance — fresh install, fresh database, full configuration via the admin UI. No client-specific branding, names, copy, or credentials may ever appear in code.

Each deployment drives a **decoupled React/Next.js frontend** (or any other JS framework) via a versioned REST API (`/api/v1/`). The Laravel app owns all data, business logic, settings, and the Filament admin; frontends are entirely separate repositories that consume the API.

Sibling product **prescribe-rx** is an internal telehealth API in the same VPC. Most clinical/order/checkout flows ultimately call prescribe-rx. Some clients will also use local checkout via NMI / Authorize.net.

## Reference codebase

`/var/www/html/prx-demo/` is a sibling Laravel app — read-only. **Do not edit anything in that directory.** It implements many patterns we mirror (NMI/Authorize.net gateways, encrypted-credential storage, DTO/Action/Service layering, `WithToast` Livewire trait, encounter wizards, `/docs/dev/` + `/docs/user/` per-feature guides). Check the prx-demo equivalent before designing a new module.

Pointers worth keeping handy:
- `prx-demo/docs/dev/SETTINGS_ARCHITECTURE_GUIDE.md` — per-install encrypted settings pattern
- `prx-demo/docs/dev/ORDER_PAYMENT_DEVELOPER_GUIDE.md` — payment gateway abstraction & merchant account lifecycle
- `prx-demo/docs/dev/INSTANCE_REDIS_HORIZON_SETUP_GUIDE.md` — multi-app Redis/Horizon prefixing on shared server
- `prx-demo/app/Services/Payments/Gateways/{NmiGateway,AuthorizeNetGateway}.php` — gateway implementations
- `prx-demo/app/Models/Payments/MerchantAccount.php` — `'encrypted:string'` casts for per-tenant API creds
- `prx-demo/app/Actions/BaseAction.php` + `app/Actions/Concerns/Transacts.php` — `tx(Closure)` transaction wrapper
- `prx-demo/app/Livewire/Traits/WithToast.php` — toast dispatch trait

## Stack (pinned)

- **PHP 8.5**, **Laravel 13.4** — framework and skeleton
- **Livewire 3.8** — used in the Filament admin only. Pinned because Filament 4 requires Livewire ^3.5 (Livewire 4 not yet supported by Filament). No public-facing Livewire components exist.
- **Alpine 3.14** + `@alpinejs/collapse` — client-side interactions inside the Filament admin
- **Tailwind CSS v3** (NOT v4) — admin panel styling. Pinned because v3 `@layer components` and CSS-variable token shape are established throughout.
- **Vite 5** + `laravel-vite-plugin` v1, **PostCSS** + `autoprefixer`
- **Filament 4.11** + **bezhansalleh/filament-shield 4.2** — admin panel
- **Spatie packages**: `spatie/laravel-data`, `spatie/laravel-permission`, `spatie/laravel-settings`
- **Laravel Horizon 5.46** — queue dashboard + worker manager
- **Laravel Sanctum** — API token authentication for frontend clients
- **Laravel Boost** (dev MCP) — installed; restart Claude Code after install for MCP tools to load

## Admin panel: Filament + Livewire for admin flows only

- **Filament 4 panel + Shield + Spatie Permissions** for all CRUD modules: CMS pages, blog, products, packages, leads, users, settings, API token management.
- **Plain Livewire components** (with strict DTO → Action → Service layering) for complex admin-side business flows: prescribe-rx wizard review, decision-tree authoring, LLM orchestration. These live inside the Filament admin shell, not on any public URL.
- **No public-facing Livewire.** The only server-rendered public page is the prescribe-rx embed handoff (`/checkout/handoff/{uuid}`), which uses a minimal layout with no branding.

## Architecture (non-negotiable)

```
React / Next.js frontend (decoupled, separate repo)
        │  HTTP  →  /api/v1/*  (Sanctum token)
        ▼
   API Controller  →  validates request
        │
        ▼
   DTO (spatie/laravel-data, validated)
        │
        ▼
   Action (single-purpose, wraps DB::transaction)
        │  ↘ Service (business logic, external API calls)
        │  ↙ returns DTO / Resource
        ▼
   API Resource  →  JSON response to frontend
```

**Admin-side flows** (Filament + Livewire) follow the same backend layers — Livewire dispatches a DTO to an Action instead of an API controller doing it:

```
Filament / Livewire (admin UI)
        │  user action
        ▼
   DTO  →  Action  →  Service  →  Response DTO  →  Toast
```

**Rules:**
- **API controllers do not touch the database directly.** They validate the request, build a typed DTO, and call an Action.
- **Livewire components do not touch the database directly.** Same rule — build a DTO, dispatch an Action.
- **Actions own DB transactions.** Use the `Transacts` trait (`$this->tx(fn() => …)`). Throw on error; the transaction rolls back.
- **Services own business logic and external integrations.** All third-party API clients (prescribe-rx, NMI, Authorize.net, Bedrock) live in `app/Services/`.
- **DTOs flow both directions.** Inputs: `{Entity}Data`. Outputs: `{Entity}Resource`. No raw arrays crossing layer boundaries.
- **Errors in Livewire/admin bubble to a reusable toast component.** Errors in API controllers return standard JSON error envelopes.

### Directory conventions

```
app/
├── Http/
│   ├── Controllers/Api/V1/{Module}/   # API controllers, thin — validate → DTO → Action → Resource
│   └── Resources/Api/V1/{Module}/     # Laravel API Resources (JSON response shape)
├── Actions/{Module}/                  # CreateXAction, etc. Extend BaseAction, use Transacts trait.
├── Services/{Module}/                 # PrescribeRx/Client, Payments/Gateways/{Nmi,AuthorizeNet}Gateway
├── Data/{Module}/                     # spatie/laravel-data DTOs: {Entity}Data (input), {Entity}Resource (output)
├── Models/                            # Eloquent. Use 'encrypted:string' / 'encrypted:json' for credentials.
├── Contracts/{Module}/                # Interfaces (e.g. PaymentGatewayInterface)
├── Enums/{Module}/                    # PHP 8.1 enums; cast model fields to enums
├── Livewire/
│   ├── Traits/WithToast.php           # Notification dispatcher trait
│   └── Admin/{Module}/               # Admin-side Livewire for complex flows inside Filament
├── Filament/                          # Filament panel resources, pages, widgets
└── View/Components/                   # Blade primitives used in Filament admin only
```

## Brand-agnostic: everything is DB-driven

**Nothing about a specific client may appear in code.** Brand name, domain, contact info, physician roster, copy, pricing, theme colors, social links, API credentials — all DB-driven, all editable via the Filament admin UI.

- Brand/site config: `spatie/laravel-settings` typed classes (`BrandSettings`, `ContactSettings`, `ThemeSettings`, `SeoSettings`). Exposed via `/api/v1/brand` and `/api/v1/theme` endpoints to the frontend — never hard-coded in templates.
- Per-client API credentials (NMI, Authorize.net, prescribe-rx, AWS Bedrock) → DB columns with `'encrypted:string'` Eloquent cast. Set in admin UI, never in `.env`.
- Physician roster, FAQ items, pricing tiers, content blocks → models with API endpoints. Not static arrays anywhere.
- Frontend theming: the frontend reads `ThemeSettings` from the API (`primary_color`, `accent_color`, `font_display`, etc.) and applies them as CSS custom properties. Each client's frontend looks unique without any backend code change.

## Multi-app server: Redis / Horizon / Supervisor prefixing

On production servers that run multiple Laravel apps sharing one Redis instance (port 6379), **all Redis-touching keys must be namespaced**.

The pattern (mirrored from prx-demo):

| Where | Variable | Default expression |
|---|---|---|
| `config/database.php` (redis options) | `REDIS_PREFIX` | `Str::slug(APP_NAME).'-database-'` |
| `config/cache.php` | `CACHE_PREFIX` | `Str::slug(APP_NAME).'-cache-'` |
| `config/session.php` | `SESSION_COOKIE` | `Str::slug(APP_NAME).'-session'` |
| `config/horizon.php` | `HORIZON_PREFIX` | `Str::slug(APP_NAME, '_').'_horizon:'` |

**Redis DB allocation (production server reference):**

| App | Default DB | Cache DB | Queue-long DB | Redis prefix | Horizon prefix | Reverb port |
|---|---|---|---|---|---|---|
| `prx-demo` | 3 | 4 | — | `prx-demo-database-` | `prx_demo_horizon:` | 8082 |
| `nuvera` | 5 | 6 | — | `nuvera-biomed-database-` | `nuvera_biomed_horizon:` | 8085 |
| **`prx-backend`** (this app) | **7** | **8** | **9** | `prx-backend-database-` | `prx_backend_horizon:` | **8093** |

**Redis connections** (in `config/database.php`):
- `default` — DB 7. Sessions, queues, locks.
- `cache` — DB 8. Cache store.
- `queue-long` — DB 9. Long-running jobs (Bedrock LLM calls, decision-tree builds). Can be repointed to ElastiCache later without touching code.

**Supervisor processes for this app** (`/etc/supervisor/conf.d/`):
- `prx-backend-horizon.conf` → `php artisan horizon`
- `prx-backend-reverb.conf` → `php artisan reverb:start --host=0.0.0.0 --port=8093`

**Reload pattern when supervisor confs change:**
```
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status | grep prx-backend
```

## External integrations

| Integration | Type | Auth | Service class |
|---|---|---|---|
| **prescribe-rx** | Internal HTTP API in same VPC. Sales-org bearer token (Sanctum). Sandbox `demo.prescribe-rx.com/api/v1`, production `prescribe-rx.com/api/v1`. **Token issued from production admin, works against either environment.** | Per-install token in `IntegrationSettings`, encrypted | `App\Services\PrescribeRx\Client` — `listEncounterTypes`, `getEncounterTypeSchema`, `submitUnifiedIntake`. See `docs/prescribe-rx/dev.md`. |
| **NMI** | Card / ACH merchant gateway | Per-install `nmi_security_key`, encrypted | `App\Services\Payments\Gateways\NmiGateway` |
| **Authorize.net** | Card merchant gateway | Per-install `authnet_api_login_id` + `authnet_transaction_key` + `authnet_signature_key`, encrypted | `App\Services\Payments\Gateways\AuthorizeNetGateway` |
| **AWS Bedrock** | LLM inference | Per-install AWS creds (or shared), encrypted | `App\Services\Llm\BedrockClient` |

Bind a common `App\Contracts\Payments\PaymentGatewayInterface` so cart/checkout depends on "the configured gateway." Per-install gateway selection lives in `MerchantAccount` model. **Mirror prx-demo's `MerchantAccount` table shape.**

Some installs will route checkout entirely through prescribe-rx instead of local NMI/Auth.net — that's a toggle in `BillingSettings`, not a code branch.

**Product / Package mapping rule:** Local product and package catalogs are manually curated per deployment (custom marketing images + brand descriptions). Each `Product` and `Package` model gets a `prescribe_rx_product_id` (and related) field that maps the local row to remote inventory in prescribe-rx. At order-submission time, the wizard translates local UUIDs to prescribe-rx IDs for the unified-intake `product_ids` array. This decouples marketing from clinical inventory.

**AI protocol generator (Bedrock-direct, deferred):** A future `App\Services\Llm\ProtocolSuggester` will call AWS Bedrock directly for a "suggestive" variant — click focus areas, return recommended products + interactions. NOT routed through the prescribe-rx HTTP API.

## API layer

All public-facing data is served via `routes/api.php` under the `/api/v1/` prefix.

- **Auth:** Laravel Sanctum. Frontend clients authenticate with a bearer token (issued in the Filament admin → API Tokens section). Token scopes gate which endpoints a client can reach.
- **Versioning:** All routes are prefixed `v1`. Breaking changes bump to `v2`.
- **Response envelope:** `{ data: ..., meta?: ..., message?: ... }` on success. `{ message: string, errors?: {...} }` on failure. Use Laravel API Resources for all responses.
- **OpenAPI docs:** Auto-generated from code with `dedoc/scramble`. Available at `/api/docs` (public) and mirrored inside the Filament admin. Postman collection exported from the same spec. Every new API route must include PHPDoc annotations so Scramble picks it up.

## Module roadmap

| Module | Scope | Status |
|---|---|---|
| **Auth + Users + Roles** | Filament panel auth, Spatie permissions via Shield, API token management | ✅ Foundation built |
| **Settings** | Brand, contact, theme, SEO, integration credentials — all via Filament settings pages | ✅ Built |
| **CMS** | Pages + typed sections + media library. Served via `/api/v1/pages/{slug}` | ✅ Built (API layer pending) |
| **Blog** | Posts, categories, tags — served via API | 🔲 Pending |
| **Catalog** | Products, Packages, Plans with prescribe-rx ref IDs — served via API | ✅ Models built, API pending |
| **Cart + Checkout** | API-driven cart state, lead capture, prescribe-rx embed handoff | 🔲 API pending |
| **Orders** | Local order shells + prescribe-rx webhook sync | ✅ Models built |
| **Leads** | Lead capture, CRM hooks, webhook delivery | ✅ Models built |
| **Wizards / Intake** | prescribe-rx embed-first approach; local lead capture + prefill; clinical intake inside embed | 🔲 Pending |
| **Clinical decision trees** | Versioned rules engine | 🔲 Deferred |
| **Bedrock LLM** | `ProtocolSuggester` service (Bedrock-direct, suggestive product recommendations) | 🔲 Deferred |
| **Merchant Accounts** | NMI / Authorize.net gateway config, per-install; `PaymentGatewayInterface` abstraction | 🔲 Pending |
| **API docs** | OpenAPI via Scramble, Postman collection, Filament + public `/api/docs` | 🔲 Next priority |

## Documentation requirement

**After every module ships, add docs under `/docs/{module}/`:**
- `user.md` — admin operator guide (what to configure, what each field does, screenshots welcome).
- `dev.md` — architecture, data model, action/service inventory, API endpoints, integration points, gotchas.
- `openapi.yaml` — per-module OpenAPI snippet (merged into the full spec by Scramble at runtime; document manually if Scramble doesn't pick up a route automatically).

Document as you build, not after. `Status: in progress` at the top is fine for partial modules. A module is not "done" until both `user.md` and `dev.md` exist.

The full live API reference is generated by `dedoc/scramble` and served at `/api/docs` (Scalar UI). Postman collection is kept in sync at `docs/api/postman_collection.json`. The goal is a self-service API reference comparable to the prescribe-rx docs at `demo.prescribe-rx.com/api.docs`.

## Workflow

- **Code style:** run `vendor/bin/pint --dirty --format agent` before finalizing PHP changes.
- **Tests:** PHPUnit (not Pest). Feature tests by default. Don't remove tests without approval.
- **Migrations:** create via `php artisan make:migration --no-interaction`. Always reversible.
- **Memory:** project memories at `/home/acappello/.claude/projects/-var-www-html-prx-backend/memory/` — already isolated per-project.
- **GitHub:** repo lives on `acappel01`. User creates the repo. Don't push or create unprompted.
- **Filament Shield ritual:** after any new Filament resource or settings page, run:
  ```
  php artisan shield:generate --all --panel=admin --no-interaction
  php artisan shield:super-admin --user=1 --panel=admin --no-interaction
  ```

## Open decisions

- ~~Redis password~~ — resolved: `REDIS_PASSWORD=null` (localhost Redis, no-auth).
- ~~Reverb~~ — installed at port 8093 for future broadcasting needs.
- **React frontend repo** — to be created under `acappel01`. Separate repo, consumes `/api/v1/`. Next.js starter lives at `docs/theme-landing-page-in-ts/protocolhrt/` as visual design reference.
- **API docs package** — `dedoc/scramble` recommended. Pending install.
- **Sanctum SPA vs token auth** — using token auth (not cookie SPA) since the React frontend is a separate domain/origin.

## First admin user

Default seed: `acappello@eloquent-media.com` / `super_admin` role. Set a strong password on first login at `/admin/login`.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- filament/filament (FILAMENT) - v4
- laravel/framework (LARAVEL) - v13
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
