# CMS — Developer Guide

> **Superseded in part (2026-07-22):** the CMS grew into the full page builder —
> hybrid section registry (code + admin-defined types), Curator media, product
> sections, global blocks, menus, layout regions, and revisions. Read
> **`docs/page-builder/dev.md`** first; this file remains accurate for the
> original Page/PageSection foundation and the 18 imported blueprint schemas.
> Note the Blade render layer described below was removed — the app is headless
> (`/api/v1/pages/{slug}`), and `PageSection.type` is now a plain string, not an
> enum cast.

**Status:** Phase 1 shipped 2026-05-01. Foundation + 18 section types + 5 fully-DB-driven Blade components. The remaining 8 imported sections render hard-coded fallbacks until refactored.
**Owners:** Anyone touching pages, sections, or content composition will read or write through this layer.

## Why this module exists

The site is a multi-tenant template that we redeploy per client. Per the project rule (CLAUDE.md → "Multi-tenancy: brand-agnostic & DB-driven"), pages composed of typed content blocks must be authored in the admin UI, not hard-coded in Blade. This module:
- Provides a `Page` model with typed `PageSection` children
- Maps 18 section types to a registry of typed form schemas + render components
- Surfaces a Filament page-builder UI (PageResource + SectionsRelationManager)
- Renders sections on the public site via `<x-cms.render-page :page="$page" />`

## Architecture

Mirrors the canonical Settings-module flow, with a registry layer for section-type polymorphism:

```
Filament PageResource (form schema)
    └─ SectionsRelationManager (per-type form schema dispatched via SectionBlueprint)
        │
        │ getState() → array
        ▼
   PageData / per-section JSON (validated in Filament + DTO)
        │
        ▼
   CreatePageAction / UpdatePageAction (Transacts trait → DB::transaction)
        │
        ▼
   pages + page_sections tables
        │
        ▼
   Public render: <x-cms.render-page :page="$page" />
       └─ <x-dynamic-component :component="'sections.'.$type" :data="$data" />
```

## Stack

- **Eloquent** — `Page` (with SoftDeletes) and `PageSection` (with Spatie\EloquentSortable) models
- **`spatie/eloquent-sortable`** — drag-to-reorder of sections within a page
- **Filament 4** — PageResource + SectionsRelationManager. Section form schemas dispatched per-type by SectionType enum.
- **`spatie/laravel-data`** — `PageData` DTO with validation attributes
- **`awcodes/filament-curator`** — media library at `/admin/media`. Section forms currently use path strings; will upgrade to `CuratorPicker` in v2.
- **Custom registry** — `App\Cms\Sections\SectionBlueprint` abstract + 18 concrete classes, mapped from `App\Enums\SectionType`.

## Files

```
app/
├── Enums/
│   ├── PageStatus.php                  # Draft / Published / Archived
│   └── SectionType.php                 # 18 cases; each case->blueprint() returns a SectionBlueprint
├── Cms/Sections/
│   ├── SectionBlueprint.php            # Abstract: type, label, icon, component, defaults, formSchema
│   ├── HeroSection.php                 # 13 imported types …
│   ├── StatsMarqueeSection.php
│   ├── ResultsStatsSection.php
│   ├── PricingTiersSection.php
│   ├── PhysiciansSection.php
│   ├── StorySection.php
│   ├── BenefitsHimSection.php
│   ├── BenefitsHerSection.php
│   ├── HowItWorksSection.php
│   ├── TestimonialsSection.php
│   ├── TransformedSection.php
│   ├── FaqSection.php
│   ├── FinalCtaSection.php
│   ├── TextBlockSection.php            # 5 generic Tailwind blocks
│   ├── ImageTextSplitSection.php
│   ├── CtaBannerSection.php
│   ├── FeaturesGridSection.php
│   └── VideoEmbedSection.php
├── Data/Pages/
│   └── PageData.php                    # Input DTO with validation
├── Actions/Pages/
│   ├── CreatePageAction.php            # All wrap DB::transaction via Transacts trait
│   ├── UpdatePageAction.php
│   └── DeletePageAction.php
├── Models/
│   ├── Page.php                        # SoftDeletes, scopePublished, generateUniqueSlug
│   └── PageSection.php                 # Sortable, casts type to enum + data to array
└── Filament/Resources/Pages/
    ├── PageResource.php
    ├── Schemas/PageForm.php            # Page-level form schema
    ├── Tables/PagesTable.php           # List view
    ├── Pages/{Create,Edit,List}Page.php # Routes Create/Edit through Action layer
    └── RelationManagers/
        └── SectionsRelationManager.php # Type-aware section form dispatcher

database/migrations/
├── 2026_05_01_192036_create_pages_table.php
└── 2026_05_01_192037_create_page_sections_table.php

resources/views/
├── components/
│   ├── cms/render-page.blade.php       # Loops sections, dispatches to <x-dynamic-component>
│   └── sections/                       # 13 imported + 5 generic Blade components
└── pages/cms/show.blade.php            # Public page view (extends layouts/app)

routes/web.php                          # Catch-all /{slug} → resolves Page + renders pages.cms.show
```

## Storage shape

### `pages` table
| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| title | string | |
| slug | string unique indexed | URL path |
| status | string indexed | `draft` / `published` / `archived` |
| template | string | Reserved for future Blade template variants. Default `default`. |
| meta_title, meta_description, og_image_path | nullable | Per-page SEO override; falls back to `SeoSettings`. |
| noindex | bool | Forces robots noindex even if global indexing is on. |
| publish_at | timestamp nullable | Future = scheduled. |
| created_by, updated_by | foreign user id, nullOnDelete | Set automatically by actions. |
| created_at, updated_at, deleted_at | timestamps | Soft-deleted. |

### `page_sections` table
| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| page_id | foreign id | cascadeOnDelete |
| type | string(64) | One of `SectionType::values()`. |
| position | unsigned int | Owned by Spatie sortable. Sorted within page (`buildSortQuery` scopes by `page_id`). |
| enabled | bool | Skipped on render when false. |
| data | json nullable | Type-specific shape; the source of truth is each `SectionBlueprint`'s defaults() + formSchema(). |
| anchor_id | string nullable | Becomes the section's HTML id for in-page links. |
| timestamps | | |

## How section dispatch works

`SectionType` enum has 18 cases. Each case's `blueprint()` method returns the corresponding `SectionBlueprint` instance via the container. The blueprint is the single source of truth for:
- **`label()`** — admin label
- **`icon()`** — heroicon name
- **`component()`** — Blade component name (default `sections.{enum-value}`)
- **`defaults()`** — initial data array used when the editor adds the section
- **`formSchema()`** — Filament form components for editing this section's data

The `SectionsRelationManager` builds a master form schema by:
1. Showing the `type` Select + common fields (anchor, enabled)
2. Spreading **all** 18 blueprints' form schemas as `Group`s, each visible only when its `type` matches
3. Using `statePath('data')` so each Group's fields write to the JSON `data` column

This keeps the editor experience uniform — pick a type, the form swaps to the right schema.

The public render in `resources/views/components/cms/render-page.blade.php`:
```blade
@foreach ($page->sections as $section)
    @if ($section->enabled)
        <x-dynamic-component
            :component="'sections.' . $section->type->value"
            :data="$section->data ?? []"
            :anchor="$section->anchor_id"
        />
    @endif
@endforeach
```

Each section's Blade component reads `$data` with `data_get($data, 'key', $fallback)`. **Sections that haven't been refactored yet** ignore the `$data` prop and render hard-coded content — this is acceptable as graceful degradation (the section still renders, just with the legacy default data).

## Section data contracts

The data shape for each section is defined in **two places that must stay in sync**:
1. The `defaults()` and `formSchema()` of the blueprint (admin UX)
2. The Blade component's `data_get()` reads (render)

When you change a field, change both. When you add a field, add to both, and add a `defaults()` entry so the form pre-fills sensibly.

### Content-free defaults policy (2026-08-15)

`defaults()` must contain **no copy** — only nulls, empty arrays, and structural flags (`theme`, `image_right`, …). Earlier blueprints shipped a legacy site's marketing copy as defaults; that meant a freshly added (or freshly seeded) section could render text belonging to another deployment. The rule now: **a section renders nothing until an operator authors its content.** Deployment-specific content lives in the DB (authored via admin, or loaded by a fill script kept in that deployment's frontend repo — see `atlas-protocol-web/scripts/atlas-home-fill.php` for the pattern). Frontends enforce the same rule by rendering nothing for content-empty section envelopes.

Blueprint shape changes shipped the same day: `hero` gained a `slides` repeater (image, heading + emphasis, description, CTA, text tone), `highlight_*` card fields, and `background_image` (static no-slides fallback; concierge-era fields removed); `physicians` entries are now name / title / specialty / image / bio / `badges[]` (legacy credentials chip, accent color, stats, and quote fields dropped); `faq` gained intro `description`, `cta_label`/`cta_url`, `image`/`image_alt`; `image-text-split` gained `lead`.

### Copy fields are rich inputs (2026-08-24)

Blueprints never construct a text input directly. Every operator-editable string
comes from `App\Cms\Support\CopyFields`, which offers exactly two kinds:

| Factory | Toolbar | Use for |
|---|---|---|
| `CopyFields::inline($name)` | bold, italic, link, undo, redo | Anything the frontend wraps in an element it picks: `heading`, `eyebrow`, `title`, `label`, `value`, `meta`, `badge`, `q`, `name`, `quote`, `text` |
| `CopyFields::prose($name)` | + h2, h3, bullet/ordered list, blockquote | Copy that gets a container of its own: `body`, `content`, `description`, `bio`, `a` |

Which kind a field takes is decided by **how the frontend renders it**, not by how
long the copy is. A `lead` is a paragraph of prose in the everyday sense but is
rendered into a styled `<p>` the component owns, so it is `inline`.

`TextInput` survives only for values that are parsed rather than read: URLs,
`image_alt`, `limit`, `icon`, numeric knobs, and CTA button labels.

**Why inline fields are normalized on save.** Hiding a toolbar button does not
remove the capability. Filament's editor registers TipTap's Heading extension with
levels 1–6 unconditionally and binds `Mod-Alt-1..6`, so a paste from Word or a
stray keyboard shortcut can put an `<h2>` into a field whose toolbar shows no
heading button. `App\Cms\Support\HtmlCopy::inline()` therefore flattens block
markup to inline HTML on dehydrate (block boundaries become `<br />`), and
`HtmlCopy::prose()` drops the empty `<p></p>` runs the editor emits for blank
lines. Both return `null` when nothing readable remains, so the content-free
defaults policy above still holds.

The guarantee this buys a frontend — inline fields never contain block markup —
is documented for external implementers in `docs/frontend/dev.md` §4a.

Trade-off accepted: rich inputs have no `maxLength`, so field length is now
editorial judgement rather than a hard stop. Character counts are misleading once
a value carries markup.

## Dataset-driven sections

Most blueprints store the content an operator types into them. A second kind stores only a
*query* and inlines content owned elsewhere at API read time, via `resolveData()`:

| Type | Source dataset | Inliner |
|---|---|---|
| `product-slider`, `product-grid`, `product-callout`, `package-slider`, `package-pricing-comparison`, `category-grid` | Catalog | `CatalogInliner` |
| `faq-categories` | Content → FAQ (`FaqCategory` / `FaqItem`) | `FaqInliner` |

`faq-categories` renders one panel per FAQ category with its published questions, plus
optional filter pills. It authors **nothing** — questions are managed once in Content → FAQ
and reused on any page built in the page builder. This is distinct from the older `faq`
blueprint, which keeps its own hand-authored `faqs` repeater for one-off marketing
accordions; both types coexist deliberately.

`FaqInliner` emits only visible categories and published items, preserves the admin's chosen
order in `manual` mode, and drops categories left with no published items so an empty panel
can never render.

**Cache coupling:** once a dataset is inlined into a cached page payload, edits to that
dataset are CMS content writes. `FaqCategory` and `FaqItem` are therefore observed by
`CmsCacheObserver` in `AppServiceProvider::configureCmsObservers()`. **Any new dataset you
inline must be added there too**, or admins will edit content and see nothing change until
the TTL expires.

## Cache invalidation across two apps

There are **two** caches between an admin save and what a visitor sees, and both must be
invalidated or content edits appear to do nothing:

| Cache | Owner | Invalidated by |
|---|---|---|
| Public API payloads (pages, layout, menus) | this app, `CmsCache` versioned namespace | `CmsCacheObserver::saved/deleted/restored` → `CmsCache::bump()` |
| Rendered pages / fetch cache | the decoupled frontend (Next.js ISR) | `FrontendRevalidator` → `RevalidateFrontendJob` → `POST {frontend}/api/revalidate` |

Bumping only the first makes the API fresh while the frontend keeps serving its cached
render for the length of its ISR window. `FrontendRevalidator` closes that gap.

**Tags, not paths.** The job sends cache tags naming *entities* (`page:faq`, `menu:main-nav`,
`layout`, `config`, `catalog`, plus the broad `cms`); the frontend attaches the matching tags
to its fetches and owns which URL each entity renders at. Sending paths would couple this
backend to one frontend's routing.

`FrontendRevalidator::tagsFor()` maps a model to its tags. Anything whose blast radius is not
a single addressable entity (global sections, flexible types, FAQ rows inlined by
`faq-categories`) falls back to the broad `cms` tag — over-purging is cheap, under-purging
shows operators stale content.

Design points worth preserving:

- **Queued.** An admin save must never block on, or fail because of, an HTTP call to another
  application. A frontend that is down or mid-deploy costs nothing; the content is saved and
  the frontend's TTL is still the backstop.
- **Coalesced.** One page save fires `Page::saved` plus one `PageSection::saved` per section.
  The service is a singleton that accumulates tags and flushes once, so that is one job, not
  a dozen.
- **Flushed from two hooks.** `terminating()` covers HTTP requests and each queued job (a
  shutdown hook would hold tags until a Horizon worker itself stopped). A
  `register_shutdown_function` fallback covers `artisan tinker`, which exits without running
  terminating callbacks — the path the deployment fill scripts use. It is skipped under test
  (the container is gone by shutdown) and wrapped in try/catch, because losing a
  revalidation is survivable but fataling at shutdown is not.
- **Optional.** No `CMS_FRONTEND_REVALIDATE_URL` (or no secret) = disabled. This backend
  ships without assuming any particular frontend exists. The URL is comma-separated, so one
  backend can drive several frontends.

Config lives in `config/cms.php` under `frontend`. The secret must match the frontend's
`REVALIDATE_SECRET`.

## Adding a new section type

1. Add a new case to `App\Enums\SectionType`.
2. Create `App\Cms\Sections\YourSection` extending `SectionBlueprint`. Implement `type()`, `label()`, `defaults()`, `formSchema()`. Optionally override `icon()`, `component()`, `description()`.
3. Map the case in `SectionType::blueprint()`'s match statement.
4. Create `resources/views/components/sections/your-type.blade.php` reading `$data` with `data_get()` fallbacks.
5. Restart the server (or `php artisan filament:cache-components`) and pick the new type from the section dropdown.

## Refactoring an imported section to be DB-driven

The imported sections (`hero`, `stats-marquee`, etc.) use top-of-file `@php` arrays. To make one DB-driven without breaking existing usage:

1. Add `@props(['data' => [], 'anchor' => null])` at the top of the Blade.
2. Move the `@php` array into a fallback chain: `$x = data_get($data, 'x', '<existing default>');`
3. Replace static strings in the markup with `{{ $x }}`.
4. Update the `id="..."` attribute to `id="{{ $anchor ?? '<existing-id>' }}"`.
5. Update the corresponding `SectionBlueprint::defaults()` to match the same default values, so admin sees them when adding the section.
6. Test: `<x-sections.your-section />` (no props) renders identically; `<x-sections.your-section :data="['x' => 'override']" />` renders the override.

**All 13 imported sections + 5 generics now read DB data with hard-coded fallbacks (as of 2026-05-01).** Adding a section in the CMS and customizing its data flows through to the public render for every type. The remaining work is the visual pass against the Next.js style reference at `/docs/atlas-dev-local` — best done section-by-section as the user reviews each.

## How creation/update routes through Actions

`CreatePage` and `EditPage` in Filament both override their respective `handleRecordCreation` / `handleRecordUpdate` methods to construct a `PageData` DTO and call `app(CreatePageAction::class)->execute($dto)`. This preserves the project rule that DB-state changes flow through the Actions layer with `DB::transaction`.

Section CRUD currently uses Filament's default Eloquent binding inside the relation manager — fast scaffolding, low value to wrap each create/update in an action when the operation is just an upsert on a single JSON column. We can revisit if business logic accretes around section operations.

## Public route

`routes/web.php` defines a catch-all `/{slug}` route that:
- Excludes reserved paths (`admin`, `horizon`, `livewire`, `media`, `reverb`) via regex
- Looks up a `Page` via `published()` scope (status=published AND publish_at null-or-past)
- 404s if not found
- Renders `resources/views/pages/cms/show.blade.php` with the page

The view extends `layouts/app` and pushes the page's per-page SEO overrides into the layout's `head` stack.

## Public site layout integration

`resources/views/components/layouts/app.blade.php` already reads `BrandSettings`, `SeoSettings`, and `ThemeSettings`. CMS pages inherit these defaults; per-page overrides come from the `Page` model's `meta_title`, `meta_description`, `og_image_path`, and `noindex` columns.

## Curator media library

Installed via `awcodes/filament-curator` v4.0.7. Available at `/admin/media`. Currently used by editors as an upload destination — paths are pasted into section form fields manually.

To upgrade a field to a one-click picker, swap `TextInput::make('image')` → `\Awcodes\Curator\Components\Forms\CuratorPicker::make('image')->multiple(false)`. The DB shape changes from string to media id, so we'd need a render-side resolver. Defer until v2.

## Tests

To be written in a follow-up turn (blocked on the same `pdo_sqlite`-or-test-DB question as the Settings module — see `docs/settings/dev.md`).

Targets:
- `CreatePageActionTest` — DTO → Action persists with auto-generated unique slug
- `PageSectionOrderingTest` — sortable scope confined to siblings of the same page
- `PublicPageRenderTest` — published page renders DB content; draft page returns 404; disabled section is skipped
- `SectionRegistryTest` — every `SectionType` case resolves to a non-null blueprint with a non-empty `formSchema()`

## What this module deliberately does NOT do

- **No live preview** of editor changes; you save then view.
- **No revisions / version history.** Coming with the Audit module.
- **No multi-language / translatable content.** If needed, add `spatie/laravel-translatable` to the Page model.
- **No per-section permissions.** Any super_admin can edit any page. Granular permissions come with Shield permission generation.
- **No caching layer** on the public render. The `with('sections')` eager-load prevents N+1 but every public hit does a DB read. If/when traffic warrants, cache by slug with a short TTL and bust on Page save.

## Future work

- Refactor the remaining 8 imported sections to consume `$data` (small per-section PRs).
- Replace `TextInput` image fields with `CuratorPicker` (depends on render-side media-id resolver).
- Page duplication action.
- Live preview iframe in the edit page.
- Block versioning + draft/publish per section (currently per-page).
- Section presets / pattern library — pre-filled section starting points beyond the type's defaults.
- ~~Migrate the home page to CMS~~ — done. `database/seeders/HomePageSeeder.php` now scaffolds a `home` Page with 8 standard section types in a typical landing order, all **content-free** (see the defaults policy above). Home is served to decoupled frontends via `GET /api/v1/pages/home`; nothing renders until content is authored.
