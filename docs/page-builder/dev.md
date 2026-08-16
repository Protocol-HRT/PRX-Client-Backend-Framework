# Page Builder — Developer Guide

Status: shipped 2026-07-22 (Phases 0–7 of the page-builder plan)

Extends the CMS module (`docs/cms/dev.md`) into a full content-first page builder:
hybrid section registry (code + admin-defined types), Curator media, product-aware
sections, global reusable blocks, menus, fixed layout regions, and page revisions.
The frontend owns 100% of presentation; this backend serves typed data payloads.

## Architecture

```
                    ┌────────────────────────────────────────┐
                    │            SectionRegistry             │  singleton
                    │  code blueprints (SectionType enum)    │
                    │  + FlexibleSectionType rows (DB)       │
                    │  → SectionDefinition per type string   │
                    └────────────────┬───────────────────────┘
                                     │ resolve(type)
   Page / PageSection ───────────────▼──────────────────────────┐
   RegionItem ────────────► SectionDataTransformer ─────────────┤→ section envelope
   GlobalSection (indirection) │  fieldKinds() walk:            │   {type, origin,
                               │   image  → MediaResolver       │    anchor, global,
                               │   products/packages → CatalogInliner │ data, schema?}
                               │   svg    → SvgSanitizer        │
                               │  resolveData() hook (queries)  │
                               └────────────────────────────────┘
```

### The `SectionDefinition` contract (`app/Contracts/Cms/SectionDefinition.php`)

Everything the builder can render implements it:
- `BlueprintDefinition` (`app/Cms/`) adapts code `SectionBlueprint`s (28 types in `app/Cms/Sections/`, registered in the `SectionType` enum).
- `FlexibleDefinition` (`app/Cms/`) adapts admin-created `FlexibleSectionType` rows.

Key methods: `type()` (string stored in `page_sections.type`), `formSchema()` (Filament
components), `defaults()`, `fieldKinds()` (dot-path → kind map driving API-side value
transformation; `*` fans over repeater items, e.g. `quotes.*.image`), `resolveData()`
(section-level hook — product blueprints run their query modes here), `isFlexible()`.

**Code wins on slug collision**; flexible slugs are validated against
`SectionRegistry::reservedSlugs()` at authoring time (belt + suspenders).

### Field kinds (transformed API-side)

| kind | stored value | emitted value |
|---|---|---|
| `image` | curator media id (int) — legacy path strings dual-read | `{id, url, alt, width, height}` or null |
| `products` / `packages` | array of ids | full catalog card array (published only, admin order kept) |
| `svg` | raw markup | sanitized via `SvgSanitizer` (DOM allowlist — see Security) |
| everything else | verbatim | verbatim |

## Data model

| table | purpose |
|---|---|
| `pages` (+ `title_banner` json) | page shell; banner: `{enabled, background_image, title_override, subtitle, intro_text, show_breadcrumbs}` |
| `page_sections` (+ `global_section_id` FK) | one section per row; `type` is a plain string (NOT the enum cast — flexible types share it) |
| `flexible_section_types` | admin-defined schemas: `{fields: [{key, kind, label, required, help, max, min, default, options, fields}]}`; slug unique, immutable |
| `global_sections` | reusable blocks: type + data; referenced by page_sections + region_items |
| `menus` / `menu_items` | slug-addressed menus; items = morph reference (aliases: page, product, package, catalog_category, blog_post, blog_category) or url/anchor; depth ≤ `config('cms.menu.max_depth')` (3) |
| `region_items` | fixed regions (`Region` enum: top_bar, header, pre_footer, footer, sidebar_left, sidebar_right); kind = section \| global_section \| menu |
| `page_revisions` | pre-edit snapshots: `{page, sections[]}` + content_hash dedupe; pruned to `config('cms.revisions.keep')` (30) |

## Services (`app/Services/Cms/`)

- **`SectionRegistry`** — singleton; memoized union of code + enabled flexible types. `flush()` after authoring writes.
- **`SectionDataTransformer`** — THE payload assembler. `transform(Collection<PageSection>)` for pages (skips disabled rows, disabled/missing globals, unknown types; batches media ids in one query); `envelopeFor(type, data, ?GlobalSection, ?anchor)` for region items.
- **`MediaResolver`** — `prime(ids)` batch load + `resolve(mixed)`. Dual-read: numeric = curator id; string = legacy storage path (`Storage::url`).
- **`CatalogInliner`** — `productsByIds` (order-preserving, published-only), `productsByMode(featured|newest|category, categoryId, limit)`, `packagesByIds` (with plans+products), single `product()`/`package()`. Uses the existing catalog API resources, so cards match `/api/v1/products` shapes.
- **`FlexibleSchemaFormBuilder`** — stored schema → Filament components (same modal UX as code sections).
- **`FlexibleSchemaValidator`** — authoring-time schema rules (snake_case keys, unique per level, no nested repeaters, select needs options).
- **`SvgSanitizer`** — DOM-walk allowlist (see Security).
- **`MenuTreeBuilder`** — one query, in-memory tree; entity links resolve the target's CURRENT route-key slug; dead/unpublished targets are dropped **with their children**.
- **`CmsCache`** — versioned namespace: keys are `cms.v{N}.…` with `config('cms.cache_ttl')` (300s) TTL; `bump()` increments `cms:version` so invalidation is instant without key bookkeeping. `CmsCacheObserver` (in `AppServiceProvider::configureCmsObservers`) is attached to Page, PageSection, FlexibleSectionType, GlobalSection, Menu, MenuItem, RegionItem.
- **`PageRevisionService`** — singleton; snapshots the page's **pre-edit persisted state** once per page per request (`PageSectionObserver` fires on `saving`/`deleting`; `UpdatePageAction` calls it directly). Content-hash dedupe; pruning. `flushMemo()` for tests.

## API endpoints (all public, throttle:api, cached via CmsCache)

| endpoint | payload |
|---|---|
| `GET /api/v1/pages/{slug}` | `{title, slug, title_banner, seo, sections: [envelope…]}` |
| `GET /api/v1/menus` / `GET /api/v1/menus/{slug}` | index / `{name, slug, items: tree}` |
| `GET /api/v1/layout` | `{regions: {top_bar: [...], header: [...], pre_footer: [...], footer: [...], sidebar_left: [...], sidebar_right: [...]}}` — all six keys always present |

### Section envelope

```json
{
  "type": "product-slider",
  "origin": "code",              // or "flexible"
  "anchor": "featured",           // page sections only; null in regions
  "global": {"id": 3, "slug": "footer-cta", "name": "Footer CTA"},  // or null
  "data": { ...transformed... },
  "schema": { "heading": {"kind": "text"},                          // flexible only
              "items": {"kind": "repeater", "fields": {"icon": {"kind": "svg"}}} }
}
```

Frontend contract: known `type` → dedicated React component; `origin: "flexible"` →
generic renderer keyed on the `schema` field-kind map, with `type`/`global.slug` as
CSS hooks (`section--trust-badges`). Menu entity links emit `{type, slug}` — the
frontend owns route patterns (`/products/{slug}`, etc.).

### Region item

`{kind: "section", section: envelope}` or `{kind: "menu", menu: {name, slug, items}}`.

## Product-aware blueprints

`product-slider` / `product-grid` share a selection model: `mode` = `manual`
(`product_ids`, order preserved) | `featured` | `newest` | `category`
(+ `category_id`), with `limit` (≤24). The query runs at API read time in
`resolveData()` → emits a `products` sibling key. `product-callout` inlines one
`product`/`package`; `package-pricing-comparison` inlines ≤3 `packages` with plans.
"Related to current product" is intentionally a frontend concern (catalog API).

Added 2026-08-15:

- `package-slider` — same selection model as `product-slider` but over packages
  (`package_ids`; `CatalogInliner::packagesByMode`), emits a `packages` sibling
  key with plans inlined. Plans now carry `price.intro` (`plans.intro_price`
  column): an introductory first-billing-cycle price, distinct from `sale`
  which discounts the ongoing recurring price.
- `category-grid` — `mode` = `manual` (`category_ids`, order preserved) |
  `all` (visible categories by position, `limit` ≤24); emits `categories`
  (CategoryResource cards). Hidden categories are always dropped.
- `product-slider` also gained presentation hints for the frontend: `variant`
  (`progressbar` | `arrows`), section-level `cta_label`/`cta_url`, and
  `card_cta_label` (link text rendered on every card).

**Caching invariant:** `CatalogInliner` serializes every payload through the
JSON pipeline (`json_encode`/`json_decode`), never bare `->resolve()` — section
payloads are cached by `CmsCache`, and unresolved nested resource collections
(plans/categories/tags) do not survive the serialize round-trip (they came back
as `__PHP_Incomplete_Class` on cache hits). Regression-tested in
`ProductSectionsTest::test_inlined_payloads_survive_cache_serialization`.

## Security

- **SVG (stored XSS)**: `SvgSanitizer` parses with DOMDocument (entities normalized
  BEFORE allow/deny — regex sanitizers are bypassable), drops non-allowlisted
  elements/attributes (`<script>`, `<style>`, `<foreignObject>`, `on*`), and keeps
  URL attributes only when fragment-only (`#icon`). Non-`<svg>` roots → null.
  Sanitization runs at API read time on every `svg`-kind field.
- **Flexible schema authoring** is validated in Actions (`FlexibleSchemaValidator`),
  not just in the Filament form.
- All new Filament resources have Shield policies (`app/Policies/Cms/`); the
  usual ritual applies after adding any resource.

## Gotchas

- `PageSection.type` is a **plain string** now. `SectionType` enum still exists as
  the code-blueprint registry, but never cast model attributes to it.
- `SectionRegistry` is a singleton — after writing flexible types outside the
  provided Actions, call `flush()`.
- `CuratorPicker` wipes unknown string states on save. `SectionImagePicker`
  (`app/Filament/Support/`) resolves legacy paths to media ids at hydration —
  always use it for section image fields. `php artisan cms:backfill-section-media
  --dry-run` migrates remaining legacy paths (section data on PageSection +
  CatalogItemSection, plus `pages.title_banner.background_image`).
- Deleting is guarded in Actions (flexible type / global block in use → exception,
  friendly toast). The DB `nullOnDelete` FKs are the safety net, not the UX.
- Revisions capture **pre-edit** state (undo semantics): restoring returns to the
  state before the burst that created the revision. Restores snapshot the current
  state first (cause `restored`), so they're undoable. Global-block references in
  snapshots also capture the block's data (`global_data`) — restore materializes a
  local copy if the block was deleted since.
- Menu depth accounting on update includes the moved item's own subtree.
- `Relation::morphMap` in `AppServiceProvider` is non-enforcing on purpose — the
  category/tag morph pivots predate it and store class names.

## Extension recipes

**New code section type**: create `app/Cms/Sections/FooSection.php` extending
`SectionBlueprint` → add a `SectionType` case + `blueprint()` match arm → declare
`fieldKinds()` for image/product/svg keys → build the matching React component.

**New field kind**: add to `FlexibleFieldKind` enum → component mapping in
`FlexibleSchemaFormBuilder` → (if transformed) branch in
`SectionDataTransformer::transformValue()` + kind lists in
`FlexibleDefinition::fieldKinds()`/`schemaMap()` → document for the frontend renderer.

**New region**: add a `Region` case — ships together with the frontend component
that renders it.

## Data-driven section types (code → DB migration)

Decided 2026-08-16: section-type *structure* migrates from code blueprints to
seeded DB rows so deployments can reshape sections without backend changes.
Runtime *behavior* stays PHP, but as a shared, declarative vocabulary instead
of per-blueprint `resolveData()` overrides.

**Vocabulary** (all usable by custom types too):

- Field kinds `product` / `package` (single pickers, inlined in place),
  `category` (raw id), `number` (min/max bounds), `color`, and `cta` — the
  full CTA group (`CtaFields::components()`, flat keys) with automatic
  add-to-cart inlining. `raw: true` on a catalog picker ships stored ids
  untouched (pair with a resolver op writing the inlined list elsewhere).
- `visible_when` on any field: ANDed `{field, operator: equals|not_equals,
  value}` conditions against sibling state (`App\Cms\Support\VisibleWhen`),
  loose string comparison because Filament re-saves selects as integers.
- Select values are re-cast to strings in the payload automatically
  (`FlexibleDefinition::resolveData`) — the integer re-save fix, now global
  for DB-defined types.
- **Resolver ops** (`App\Services\Cms\SectionResolverOps`), declared per type
  in `schema.resolvers`, run after field-kind transforms:
  `inline_product|inline_package|inline_products|inline_packages`
  (`input`/`output` keys), `products_by_mode|packages_by_mode` (the slider
  manual/featured/newest/category convention; `output`, optional `*_key`
  overrides), `categories`, `resolve_cta` (`path: ''` or `items.*`),
  `cast_string` (`path`). The admin form round-trips `schema.fields` only —
  `UpdateFlexibleSectionTypeAction` carries stored resolvers forward.

**Shadow/active modes** (`flexible_section_types.mode`): `SectionTypeSeeder`
mirrors code blueprints as `shadow` rows — visible in Custom Section Types
(badge "Shadow · code-backed") but inert; the code definition keeps serving.
Promoting a row (table action, or `SetFlexibleSectionTypeModeAction`) flips
registry precedence: an **active** DB row now wins the slug, making the
type's fields editable in the admin. Revert at any time — the registry falls
back to code whenever no active row holds the slug. Admin creation still
rejects reserved slugs; only the seeder introduces colliding rows.

**Golden parity** (`SectionTypeSeedParityTest`): a seed may only be promoted
once its test proves the seeded definition matches the blueprint —
byte-identical `data` payload for a representative fixture, plus equal
defaults, fieldKinds, and field inventory. Seeded so far: `text-block`,
`cta-banner`, `video-embed`, `features-grid`, `highlight-banner`,
`product-slider` (proves `products_by_mode` + `visible_when` + `raw`).

**Remaining to seed** (next sessions): the other 23 blueprints. The complex
ones map as: catalog sliders/grids → `*_by_mode` ops; `product-callout` →
`inline_product`/`inline_package`; `category-grid` → `categories`;
`benefits-diagram`/`image-callout-banner` → `cta` kind (or `resolve_cta` at
`callouts.*`) + `cast_string` for slot positions. Form-layout groups degrade
to flat forms; conditional visibility survives via `visible_when`. Frontend
impact of promotion: envelope `origin` flips to `flexible` and a `schema`
map appears — atlas keys components by `type` only, so no changes needed.
