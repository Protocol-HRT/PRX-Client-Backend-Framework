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

## Requirements

| | |
|---|---|
| PHP | **8.3+** (8.5 in production) with `bcmath`, `intl`, `mbstring`, `redis`, `zip`, `gd` |
| Composer | 2.x |
| MySQL | 8.0+ (MariaDB 10.6+ works; the schema uses JSON columns) |
| Redis | 6+ — queues, cache and broadcasting all use it |
| Node | 20+ (admin theme assets only; the public frontend is a separate app) |
| Web server | Apache or nginx, serving `public/` |
| Supervisor | for the queue worker and websocket server |

## Install

```bash
git clone <this-repo> prx-backend && cd prx-backend
composer install
npm install && npm run build          # admin panel assets

cp .env.example .env
php artisan key:generate              # APP_KEY — see the warning below
```

> **`APP_KEY` encrypts every credential in the database.** Integration keys, merchant
> credentials and prescribe-rx tokens are all `encrypted` casts. Back it up with the database:
> restoring a dump against a *different* key leaves every secret permanently unreadable, and
> there is no recovery. Never rotate it without re-encrypting first.

Set at minimum in `.env`:

```ini
APP_NAME="Your Brand"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=prx_backend
DB_USERNAME=…
DB_PASSWORD=…

# Redis-backed everything. See "Running more than one install" below.
REDIS_HOST=127.0.0.1
REDIS_DB=0
REDIS_CACHE_DB=1
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# The first admin account, created by the seeder. Not committed.
ADMIN_NAME="Your Name"
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=…
```

Then:

```bash
php artisan storage:link              # public/storage -> storage/app/public
php artisan migrate --seed            # schema + the seeders in DatabaseSeeder:
                                      #   AdminUserSeeder, BaseRolesSeeder,
                                      #   CatalogVocabularySeeder, SectionTypeSeeder
php artisan optimize:clear
```

Log in at `/admin` with the `ADMIN_*` credentials. The seeders create a `super_admin`
(Gate-level bypass) plus a default role matrix — `admin`, `content_editor`, `catalog_manager`,
`support` — which you can tune in the Roles UI.

Optional demo content, neither required nor client-specific:

```bash
php artisan db:seed --class=DevCatalogSeeder   # neutral demo products/packages
php artisan db:seed --class=HomePageSeeder     # placeholder home page
```

**After adding any Filament resource or page**, run
`php artisan shield:generate --all --panel=admin --no-interaction` or it will be invisible to
every role except `super_admin`.

## Web server

The document root is **`public/`**, never the repo root. `FollowSymLinks` is required because
`public/storage` is a symlink created by `storage:link`.

<details>
<summary>Apache</summary>

```apache
<VirtualHost *:443>
    ServerName admin.example.com
    DocumentRoot /var/www/prx-backend/public/

    <Directory /var/www/prx-backend/public/>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Reverb websockets, if you use broadcasting. Match the port in .env.
    RewriteEngine on
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^/app/?(.*) ws://127.0.0.1:8080/app/$1 [P,L]
    ProxyPass        /app ws://127.0.0.1:8080/app
    ProxyPassReverse /app ws://127.0.0.1:8080/app
</VirtualHost>
```

Needs `a2enmod rewrite proxy proxy_http proxy_wstunnel headers`.
</details>

<details>
<summary>nginx</summary>

```nginx
server {
    server_name admin.example.com;
    root /var/www/prx-backend/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    # Media uploads. nginx defaults to 1 MB and returns 413 before PHP is
    # reached, which looks like a broken uploader rather than a limit.
    client_max_body_size 32M;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /app {                      # Reverb websockets
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```
</details>

## Background processes

**Both are required, not optional.** Workflows, cache invalidation and outbound integrations
all run on the queue — without a worker they enqueue silently and never execute, and the admin
shows no runs and no errors.

`/etc/supervisor/conf.d/prx-backend-horizon.conf`:

```ini
[program:prx-backend-horizon]
process_name=%(program_name)s
command=php /var/www/prx-backend/artisan horizon
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/prx-backend/storage/logs/horizon.log
stopwaitsecs=3600
```

`/etc/supervisor/conf.d/prx-backend-reverb.conf` (only if you use broadcasting):

```ini
[program:prx-backend-reverb]
process_name=%(program_name)s
; Loopback only — the web server reverse-proxies wss://…/app to it.
command=php /var/www/prx-backend/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/prx-backend/storage/logs/reverb.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

Horizon supervises two queues, both declared in `config/horizon.php`: `default`, and
`workflows` for automation chains. The workflows queue has its own connection in
`config/queue.php` with a longer `retry_after` than its worker timeout — **do not retune one
without the other**; a chain that outlives its reservation gets marked failed without running
while another worker completes it. A test pins the relationship.

**Horizon workers hold the code they booted with.** Every deploy needs
`php artisan horizon:terminate` (supervisor restarts it) or queued work runs the previous
release.

## Running more than one install on one host

Laravel's defaults **collide**, and the symptoms are baffling rather than loud — two apps
sharing a queue, jobs vanishing into the wrong worker, one app's websocket clients receiving
another's events. Before starting supervisor, check every one of these differs from every
other install on the box:

```
REDIS_DB   REDIS_CACHE_DB   REDIS_PREFIX   HORIZON_PREFIX
SESSION_COOKIE   REVERB_PORT   REVERB_APP_ID / KEY / SECRET
```

`REDIS_PREFIX` namespacing alone is **not** sufficient — Horizon and Reverb do not fully
respect it, and a cloned `.env` carries the previous install's Reverb credentials byte for
byte, which silently joins the two apps' websocket channels. Generate fresh Reverb credentials
per install; never copy them.

## Upgrading

```bash
php artisan down
git pull && composer install --no-dev -o
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan horizon:terminate          # workers must pick up the new code
php artisan up
```

Run `php artisan shield:generate --all --panel=admin --no-interaction` after any release that
adds admin screens.

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
| Quiz / lead funnel (typed questions, branching, eligibility gate) | ✅ | [`docs/leads`](docs/leads) |
| Workflows (operator-built automation, queued, with a run log) | ✅ | [`docs/workflows`](docs/workflows) |
| Integrations (provider catalogue, capability routing, PHI boundary) | ✅ | [`docs/integrations`](docs/integrations) |
| Clinical decision trees · Bedrock LLM protocol suggester | 🔲 deferred | — |

Full index with one-line descriptions: [`docs/README.md`](docs/README.md).

## Development

```bash
php artisan test --compact            # PHPUnit (feature tests by default)
vendor/bin/pint --dirty               # code style — run before committing
php artisan shield:generate --all --panel=admin --no-interaction   # after adding Filament resources/pages
```

Conventions that are non-negotiable: controllers and Livewire never touch the DB directly (DTO → Action → Service), Actions own transactions, integrations live in `app/Services`, credentials use `encrypted:*` casts, and no client-specific value ever appears in code. See [`docs/architecture/dev.md`](docs/architecture/dev.md).

## Production notes

- **Cache only resolved arrays, never Eloquent models.** Serialized models break across PHP
  runtimes sharing one Redis instance, and the failure surfaces as an unrelated deserialization
  error somewhere else entirely.
- **Sharing a Redis instance between installs needs more than key prefixes** — see "Running
  more than one install on one host" above. Prefix namespacing does not cover Horizon or
  Reverb, and it was an assumption on this point that let two apps on one host share a
  websocket server and queue databases.
- **A failed deploy leaves workers on old code.** `horizon:terminate` is part of the deploy,
  not an optional tidy-up.
- **Health data**: if this install collects it, read
  [`docs/integrations/user.md`](docs/integrations/user.md) before connecting a marketing
  platform. Destinations are not permitted to receive it until somebody attests, by name, that
  an agreement covers it.
