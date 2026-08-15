# Settings — Developer Guide

**Status:** Shipped 2026-05-01.
**Owners:** All engineers touching the public site or admin panel will eventually read or write through this layer.

## Why this module exists

This codebase is a multi-tenant template — we redeploy it for each client. Per the project rule (CLAUDE.md → "Multi-tenancy: brand-agnostic & DB-driven"), **nothing brand-specific may be hard-coded**. Brand name, contact info, theme colors, SEO defaults, and analytics IDs are stored in the database and editable in `/admin/settings/*`. Operations staff (not engineers) own these knobs in production.

## Architecture

This is the canonical example of the project's mandatory layering. Touch this if you need a reference implementation of DTO → Action → Service → DB.

```
Filament Page (form schema + state)
        │
        │ getState() → array
        ▼
   spatie/laravel-data DTO (validateAndCreate → throws on bad input)
        │
        ▼
   Action (Transacts trait, wraps DB::transaction)
        │
        ▼
   spatie/laravel-settings Settings class → settings table
        │
        ▼
   Public Blade layout reads via app(BrandSettings::class) — instant reflect
```

## Stack

- **`spatie/laravel-settings`** for typed Settings classes + the `settings` table
- **`spatie/laravel-data`** for input DTOs with validation attributes
- **Filament 4** custom Page subclasses for the admin UI (under the **Settings** navigation group)
- **`Transacts` trait** for the action's `DB::transaction` wrapper

## Files

```
app/
├── Settings/
│   ├── BrandSettings.php          # group: brand
│   ├── ThemeSettings.php          # group: theme
│   ├── ContactSettings.php        # group: contact
│   └── SeoSettings.php            # group: seo
├── Data/Settings/
│   ├── BrandSettingsData.php      # input DTO with validation
│   ├── ThemeSettingsData.php
│   ├── ContactSettingsData.php
│   └── SeoSettingsData.php
├── Actions/Settings/
│   ├── UpdateBrandSettingsAction.php  # DB::transaction-wrapped
│   ├── UpdateThemeSettingsAction.php
│   ├── UpdateContactSettingsAction.php
│   └── UpdateSeoSettingsAction.php
├── Actions/
│   ├── Concerns/Transacts.php     # tx(Closure) wrapper, mirrored from prx-demo
│   └── Exceptions/ActionException.php
└── Filament/Pages/Settings/
    ├── ManageBrand.php            # /admin/settings/brand
    ├── ManageTheme.php            # /admin/settings/theme
    ├── ManageContact.php          # /admin/settings/contact
    └── ManageSeo.php              # /admin/settings/seo

database/settings/                 # spatie/laravel-settings migration dir
├── 2026_05_01_180000_create_brand_settings.php
├── 2026_05_01_180001_create_theme_settings.php
├── 2026_05_01_180002_create_contact_settings.php
└── 2026_05_01_180003_create_seo_settings.php

resources/views/
├── components/layouts/app.blade.php       # reads BrandSettings + SeoSettings + ThemeSettings
└── filament/pages/settings/
    ├── manage-brand.blade.php
    ├── manage-theme.blade.php
    ├── manage-contact.blade.php
    └── manage-seo.blade.php

tests/Feature/Settings/
└── BrandSettingsTest.php
```

## Storage shape

`spatie/laravel-settings` stores rows in a single `settings` table:

| Column | Example |
|---|---|
| `group` | `brand` |
| `name` | `name` |
| `payload` | `"PrescribeRx Open Source Backend"` (JSON-encoded value) |
| `locked` | `false` |

One row per group/name combination. Each Settings class declares its `group()` (e.g. `BrandSettings::group()` returns `'brand'`) and its public properties become the `name` values.

## Read path

The Settings classes are bound as singletons by `spatie/laravel-settings`'s service provider. Resolving via the container reads from the database (or cache, see below) once and reuses the instance for the request:

```php
// Anywhere in PHP — controllers, Livewire, Blade @php blocks, services
$brand = app(\App\Settings\BrandSettings::class);
echo $brand->name;
```

In Blade, prefer the `@php` block at the top of a layout/component for clarity:

```blade
@php
    $brand = app(\App\Settings\BrandSettings::class);
    $seo   = app(\App\Settings\SeoSettings::class);
@endphp
<title>{{ $title ?? $seo->default_meta_title }}</title>
<meta property="og:site_name" content="{{ $brand->name }}">
```

`resources/views/components/layouts/app.blade.php` is the reference implementation — the `<title>`, meta description, OG tags, favicon, and `noindex` toggle all flow from settings.

## Write path

Always go through the Action. **Do NOT** call `$brand->save()` directly outside an Action — you lose the transaction wrapper and bypass DTO validation.

```php
use App\Actions\Settings\UpdateBrandSettingsAction;
use App\Data\Settings\BrandSettingsData;

$data = BrandSettingsData::validateAndCreate($request->all());
$updated = app(UpdateBrandSettingsAction::class)->execute($data);
```

DTO validation throws `Illuminate\Validation\ValidationException` on bad input. The Filament page catches it via `try/catch` and dispatches a danger Notification.

### Why `validateAndCreate` and not `from`

`Data::from(...)` is permissive — it constructs the DTO from arbitrary input without applying the validation attributes. `Data::validateAndCreate(...)` does construct + validate + throw. Pages and any direct caller use `validateAndCreate`. Internal callers that already trust their input (e.g. seeders) may use `from` knowingly.

## Adding a new settings group

1. **Create the Settings class** under `app/Settings/{YourGroup}Settings.php` extending `Spatie\LaravelSettings\Settings`. Declare typed public properties. Override `group(): string` to return a unique slug.
2. **Create the seed migration** under `database/settings/YYYY_MM_DD_HHMMSS_create_{your_group}_settings.php` extending `Spatie\LaravelSettings\Migrations\SettingsMigration`. Use `$this->migrator->add('group.key', $defaultValue)` for each property in `up()` and a matching `deleteIfExists` in `down()`. Run `php artisan migrate`.
3. **Create the input DTO** under `app/Data/Settings/{YourGroup}SettingsData.php` extending `Spatie\LaravelData\Data`. Match the property set 1:1 with the Settings class. Add validation attributes (`#[Required]`, `#[Email]`, `#[Url]`, `#[Regex(...)]`, etc.).
4. **Create the Action** under `app/Actions/Settings/Update{YourGroup}SettingsAction.php`. Inject the Settings class, use the `Transacts` trait, copy DTO properties onto the Settings instance, call `save()` inside `tx()`.
5. **Create the Filament page** by extending `App\Filament\Pages\Settings\BaseSettingsPage` (NOT `Filament\Pages\Page` directly — the base sets `$navigationGroup = 'Settings'`, defines the standard `content()` schema that wraps the form with a save-actions footer, and points `$view` at `filament.pages.settings.base`). Override `$navigationIcon`, `$slug`, `$navigationSort`, `$navigationLabel`, `$title`, plus the `mount()` form-fill, the `form(Schema $schema)` schema, and the `save()` method that goes DTO → Action → Notification.
6. **No per-page Blade view is needed** — `BaseSettingsPage::$view` points at the shared `resources/views/filament/pages/settings/base.blade.php` which renders `{{ $this->content }}` inside `<x-filament-panels::page>`. Filament 4 builds the form + save-button footer from the schema returned by the base's `content()` method.
7. **(Optional) wire it into the public layout** if the values are user-visible — read the Settings instance via `@php` at the top.
8. **(Optional) regenerate Shield permissions** so the new page gets a `view_*` permission: `php artisan shield:generate --pages` (or all). Re-assign the role(s) that should access the new page.
9. **Test it**: feature test that runs the action through a DTO and asserts the persisted value, plus an HTTP test that asserts the public site reflects the change.
10. **Document it**: add a row to `docs/settings/user.md` and update the field tables.

## Performance / caching

- `SETTINGS_CACHE_ENABLED` (env, default `false`) — turns on the spatie/laravel-settings cache layer. Recommended for production.
- `SETTINGS_CACHE_MEMO` (env, default `false`) — uses Laravel 12.9+ memoized cache so resolved values stay in memory for one request. Recommended.
- The `settings` table is small (≤100 rows). The reads are cheap, but cache turns on full bypass which matters at high RPS.

## What this module deliberately does NOT do

- **No multi-tier waterfall** (User > Client > Provider > System) — that's prx-demo's pattern for its multi-tenant telehealth shape. We're single-tenant-per-deploy here.
- **No encrypted credentials yet** — third-party API keys (NMI, Auth.net, prescribe-rx, Bedrock) live in their own modules with their own per-tenant models that use Eloquent's `'encrypted:string'` cast (mirroring prx-demo's `MerchantAccount`). Settings groups are for non-secret config.
- **No per-user preferences** — those will live on the `User` model when User Settings ships.

## Tests

`tests/Feature/Settings/BrandSettingsTest.php` covers:
- `test_update_action_persists_brand_settings_through_dto` — happy path
- `test_invalid_payload_is_rejected_by_dto` — `validateAndCreate` throws on bad input
- `test_homepage_renders_brand_and_seo_from_settings` — public site reflects DB

**To run them, you need either `pdo_sqlite` (in-memory test DB)** — install via `sudo apt install php8.5-sqlite3 && sudo systemctl reload php-fpm` — **or a dedicated MySQL test DB** named `protocol-hrt-website-test` plus a phpunit.xml override pointing to `mysql`. As of 2026-05-01 neither is set up; the tests are syntax-clean but un-run.

## Future work

- Image-upload field for Brand → Logo / Favicon / Hero (depends on Media Library module)
- Audit log on settings changes (depends on Audit module)
- Per-page meta override UI (depends on CMS module)
- Theme preview pane in `/admin/settings/theme` showing live color tokens applied to a sample card
