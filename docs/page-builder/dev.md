# Page Builder — Developer Guide

Status: shipped 2026-07-22 (Phases 0–7 of the page-builder plan)

Extends the CMS module (`docs/cms/dev.md`) into a full content-first page builder:
hybrid section registry (code + admin-defined types), Curator media, product-aware
sections, global reusable blocks, menus, fixed layout regions, and page revisions.
The frontend owns 100% of presentation; this backend serves typed data payloads.

## Custom HTML — the escape hatch

`html-block` renders an operator's markup **verbatim and unsanitised**.

It exists because the rich-text fields cannot do this and should not try. A rich editor treats
pasted markup as TEXT: TipTap wraps each line in a paragraph and escapes the angle brackets, so
an operator who pastes a custom layout gets a page displaying its own source. That is not a
frontend rendering bug — the frontend is faithfully showing what got stored.

**Trust model:** identical to `custom_css` and `custom_head_scripts`. Permission-gated content
from this install's own admin, injected as-is. With this block available, `Update:Page` is
equivalent to script access on the public site — grant it accordingly.

Prefer a real section type when one fits. This is for the page needing a layout nothing else
provides — a legal page with its own contents sidebar, a vendor's embed snippet — not a way
around building a blueprint for something the site does repeatedly.


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
- `BlueprintDefinition` (`app/Cms/`) adapts code `SectionBlueprint`s (29 types in `app/Cms/Sections/`, registered in the `SectionType` enum).
- `FlexibleDefinition` (`app/Cms/`) adapts admin-created `FlexibleSectionType` rows.

Key methods: `type()` (string stored in `page_sections.type`), `formSchema()` (Filament
components), `defaults()`, `fieldKinds()` (dot-path → kind map driving API-side value
transformation; `*` fans over repeater items, e.g. `quotes.*.image`), `resolveData()`
(section-level hook — product blueprints run their query modes here), `isFlexible()`,
`hasIntrinsicContent()`.

#### Functional sections — `hasIntrinsicContent()`

Added 2026-08-28 with the `quiz` type, and the only implementor so far.

`has_content` normally asks *"did an operator author anything here"*, and a section that
answers no is dropped so an empty scaffold cannot reach a live page. That is right for every
editorial type and wrong for a **functional** one. The `quiz` section's content is the wizard it
mounts; the heading above it is optional decoration. Judged on authored copy alone it reports
`has_content: false`, gets dropped, and an operator who added it and wrote no heading watches
the section silently vanish — the least debuggable failure this CMS has.

So a blueprint may declare `hasIntrinsicContent(): true`, and the transformer ORs it with the
authored-content walk at both call sites in `SectionDataTransformer`.

**It is a claim that the COMPONENT renders something on its own.** Never set it to work around
an empty-payload bug on an editorial type — that re-opens the exact hole `SectionContent`
exists to close. `FlexibleDefinition` returns `false` unconditionally and cannot opt in: a
flexible type is a field list an operator assembled, with no component behind it, so "content"
there can only mean what they typed.

Pinned by `SectionHasContentTest::test_a_functional_section_reports_content_with_an_empty_payload`
and its companion asserting the flag does not leak to editorial types.

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

## Presentation knobs, style knobs and sub-blocks

Every section carries a shared vocabulary of layout and style settings that no blueprint
declares. `SectionFormBuilder` injects the same two panels into every type, so the keys land
in `data` alongside the type's own fields. Frontend contract: `docs/frontend/dev.md` §4;
operator guide: `docs/cms/user.md`.

### The registry is `App\Cms\Support\LayoutFields`

| Constant | Holds | Why it exists |
|---|---|---|
| `KEYS` | the live knob vocabulary | `presentationKeys()` unions it in, and `SectionContent` counts anything **not** a presentation key as authored content |
| `RETIRED_KEYS` | knobs whose control is gone | a stored value outlives its control — see below |
| `IMAGE_KEYS` | knobs holding a media id | `SectionDataTransformer` unions it in, or the value serves as a bare integer instead of a resolved image |

**Responsive overrides are flat suffixed keys** (`content_inset_md`, `style_padding_top_lg`),
not a nested shape. The base key **is** the mobile value, so existing rows needed no migration
and there is no serve-time shim. `hasContent()` skips presentation keys **by name, never by
value shape** — which is the property that made flat cheaper than nested, and is why listing a
key in `KEYS` is the whole of making it presentation. The suffix scale is `_breakpoints.scss`'s
(Bootstrap 5.3: `md` 768, `lg` 992); do not invent another.

**Style keys are namespaced `style_*`, and that is load-bearing.** `background_image` was tried
bare and collided head-on with a real content field of the same name on `hero`, `cta-banner`
and `image-callout-banner`: those sections' actual background images were reclassified as
presentation, and a hero carrying only a background image would have reported
`has_content: false` and vanished from a live page. `LayoutFieldCollisionTest` enforces the
prefix. Note that the panel a control appears in is unrelated to its key prefix — vertical
padding is `style_padding_top` but lives in **Layout & spacing**, because that is where an
operator looks for it.

**There is no `style_padding_left` / `_right`, and it is a correctness decision rather than a
scope cut.** Padding on the knob wrapper narrows `.sx-section`'s containing block, and the
section's bleed is a fixed `-1 * --page-gutter` that recovers the gutter but not the knob — so
horizontal padding would leave every self-painting band inset from the viewport edge by exactly
the amount chosen. The horizontal edges belong to `content_inset`, which acts inside
`.sx-section` and cannot do that.

### Two defaults mechanisms, and only one of them reaches the payload

- **`LayoutFields::applyDefaults()`** merges a definition's `layoutDefaults()` at serve time,
  and merges **only** keys in `KEYS` — a definition cannot smuggle content into a payload
  through it.
- **A blueprint's own `defaults()` are NOT merged into served data.** They drive the admin form
  and the presentation classification, nothing else. **So the frontend owns the fallback for
  any defaulted blueprint field**: a section saved through the admin carries the key and one
  written by a fill script does not, and both must render identically.

That asymmetry is invisible and easy to assume away — it is pinned by a test asserting the key
is **absent** from a served, untouched hero, which fails loudly if the merge behaviour ever
changes and makes a frontend fallback unreachable. Keep such a test whenever a blueprint field
gains a non-null default.

### Presentation classification is automatic for defaulted fields

`DeclaresPresentationKeys::presentationKeys()` unions `KEYS` + `RETIRED_KEYS` + **every
blueprint field with a non-empty default** — the filter excludes `null`, `''` **and** `[]`, so
an empty string or empty array default does *not* classify the field as presentation. It leans on an invariant the CMS already enforces
— `defaults()` contains no copy, only nulls, empty arrays and structural flags — so a key with
a non-null default *is* a structural flag and classifies itself.

**Consequence worth knowing before you add a per-type presentation field:** give it a non-empty
default — a real token, not `''` or `[]` — and it needs no `KEYS` entry, no new shared surface, and carries none of the retired-key
hazards. `hero.highlight_position` is the worked example. Set that default to `null` and an
untouched hero starts reporting `has_content: true`.

A blueprint with a structural key that has **no** default must override the trait and merge its
own.

### Palette-valued knobs must be declared twice

`PaletteUsage::KEYS` is the subset of `LayoutFields::KEYS` whose value is a palette **name**.
Deleting — or **renaming**, since sections store the name and not an id — a colour still in use
cannot be recovered from on the frontend: `--sx-bg` resolves to an undefined custom property,
the declaration computes to `unset`, and the band renders transparent while its marker class
still claims a colour was chosen. So the guard has to run before the save.

Add a name-valued knob to `KEYS` without adding it to `PaletteUsage::KEYS` and the guard goes
blind to it. `PaletteDeletionGuardTest` pins the two as a subset relation.

The usage walk is **PHP, not SQL**, deliberately: a child block's knobs live inside the parent's
`data` JSON, so a `LIKE '%sand%'` cannot tell a card's background from the word appearing in
authored copy. Do not optimise it into one.

### Typed sub-blocks

A section's `data` may hold `children` — a repeater of `{type, data}` items, served as
`{type, data, has_content}`. Filament's `Builder` is used rather than a Repeater with a type
select because its persisted shape **is** the child envelope, so there is no discriminator to
invent and keep in sync.

- **`SectionChildren::KEY` is reserved, not a layout key.** Children are **content**: the key
  must stay countable by `hasContent()`, and each child is judged by its own verdict. Putting
  it in `LayoutFields::KEYS` would make a section holding nothing but children render as empty.
- It is guarded twice, like the style prefix: `LayoutFieldCollisionTest` asserts no code
  blueprint declares it, and `FlexibleSectionTypeForm::reservedFieldKeys()` refuses it as an
  operator types it — the only place a runtime-created flexible type can be caught.
- **The knob panels are reused verbatim for children** via `blockFor()`, with a `$nested` flag
  for the differences. Today that flag drops `flush` from a child's horizontal inset, because a
  child is not adjacent to the page edge and the option would be inert. Anything else that
  reads differently at the two levels — help text especially — needs the same treatment.
- Today: one block type (`TestimonialBlock`), one section that holds children (`HeroSection`).

### A blueprint field also has to reach the seeded mirror

Several code types have a seeded **flexible mirror** row, and `SectionTypeSeedParityTest` pins
the two together — **adding a blueprint field without adding it to `SectionTypeSeeder` fails
the suite**, with a bare array diff for a failure message. It is not obvious from the blueprint
you are editing, so check it whenever you add a field.

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

**New layout/style knob**: add the control to `SectionFormBuilder` **and** the key to
`LayoutFields::KEYS` *in the same commit* → if it holds a palette name, add it to
`PaletteUsage::KEYS` → if it holds a media id, add it to `LayoutFields::IMAGE_KEYS` → if it
should differ by breakpoint, add the `_md` / `_lg` keys too → emit the class and custom
property in the frontend's `lib/sectionKnobs.js` and read it in `_layout-frame.scss` /
`_sub-blocks.scss`. A knob in the form but missing from `KEYS` makes an untouched scaffold
look authored the moment an operator nudges it, and the empty section leaks onto a live page.

**Retiring a layout knob**: removing it from `KEYS` is **not** enough, and this is written down
because the assumption was caught doing damage. A stored value outlives its control, so the key
must (1) move to `LayoutFields::RETIRED_KEYS` — or `hasContent()` starts counting it as authored
copy and an empty scaffold renders on a live page — and (2) stay in
`FlexibleSectionTypeForm::reservedFieldKeys()`, or an operator can create a flexible field with
that name and have the content silently dropped. Retiring `extra_padding` left values in 13 rows
of stored JSON and flipped a deliberately-empty bench section from `has_content: false` to
`true`. A retired key stays listed permanently, or until the values are cleaned out of every
row.

## Data-driven section types (code → DB migration)

Decided 2026-08-16: section-type *structure* migrates from code blueprints to
seeded DB rows so deployments can reshape sections without backend changes.
Runtime *behavior* stays PHP, but as a shared, declarative vocabulary instead
of per-blueprint `resolveData()` overrides.

**Vocabulary** (all usable by custom types too):

- Field kinds `product` / `package` (single pickers, inlined in place),
  `category` (raw id), `categories` (multi picker, raw ids — pair with the
  `categories` op), `number` (min/max bounds), `color`, and `cta` — the
  full CTA group (`CtaFields::components()`, flat keys) with automatic
  add-to-cart inlining. `raw: true` on a catalog picker ships stored ids
  untouched (pair with a resolver op writing the inlined list elsewhere).
- **Repeaters nest one level** (timeline's `steps.*.bullets`); a third level
  is rejected. `simple: true` on a repeater mirrors Filament's `->simple()`
  storage — items hold the single child field's bare value, a flat scalar
  list (physicians' `badges`, pricing-tiers' `features`/`guarantees`).
  Define exactly one child field.
- **`group` kind**: a fixed-shape nested map rendered as a
  `Fieldset->statePath(key)` — the data-side of code blueprints' dotted
  field names (`peptide_card.title`). Children may include repeaters; groups
  never nest inside repeaters or other groups. The Section Types catalog
  inventories group children under their dotted names.
- `visible_when` on any field: ANDed `{field, operator: equals|not_equals,
  value}` conditions against sibling state (`App\Cms\Support\VisibleWhen`),
  loose string comparison because Filament re-saves selects as integers.
- Select values are re-cast to strings in the payload automatically
  (`FlexibleDefinition::resolveData`) — the integer re-save fix, now global
  for DB-defined types. The cast recurses into repeater items and groups, so
  child selects (e.g. `callouts.*.position`) need no `cast_string` resolver.
- **Resolver ops** (`App\Services\Cms\SectionResolverOps`), declared per type
  in `schema.resolvers`, run after field-kind transforms:
  `inline_product|inline_package|inline_products|inline_packages`
  (`input`/`output` keys), `products_by_mode|packages_by_mode` (the slider
  manual/featured/newest/category convention; `output`, optional `*_key`
  overrides), `categories`, `resolve_cta` (`path: ''` or `items.*`),
  `cast_string` (`path`). Any op may carry a `when` list (visible_when
  shape, evaluated against the payload): while conditions fail the op is
  skipped and its `output` key is written as null — replicating conditional
  blueprint inlining like product-callout's `item_type` branch. The admin
  form round-trips `schema.fields` only — `UpdateFlexibleSectionTypeAction`
  carries stored resolvers forward.

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
defaults, fieldKinds, and field inventory (top-level names; the inspector
expands a `cta` kind to its flat keys and flattens group children to dotted
names so both origins inventory identically).

**All 28 code blueprints are now mirrored as shadow seeds** (2026-08-16):
the original six, the 15 content types (final-cta, stats-marquee,
results-stats, story, how-it-works, testimonials, timeline,
image-text-split, physicians, benefits-him/her, transformed, pricing-tiers,
faq, hero), the five catalog-driven types (product-grid, package-slider,
package-pricing-comparison, product-callout, category-grid), and the two
CTA-bearing types (benefits-diagram, image-callout-banner). Every seed has
a parity test; promotion is an admin decision per deployment. Form-layout
Sections degrade to flat forms (accepted); conditional visibility survives
via `visible_when`. Frontend impact of promotion: envelope `origin` flips
to `flexible` and a `schema` map appears — atlas keys components by `type`
only, so no changes needed.

**Authoring-UI subset caveat**: the admin schema editor cannot express
everything seeds can (child select options, nested repeater grandchildren,
group children). Editing a promoted seed's *fields* in the admin round-trips
only what the editor renders — the validator rejects saves that would strip
a required construct (e.g. a repeater losing all children), so a failed save
here means "this schema is seed-managed", not data loss.
