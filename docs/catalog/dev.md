# Catalog Module — Developer Guide

**Status:** Shipped 2026-06-22 · Clinical schema expansion + PRX mapping admin shipped 2026-08-16

## Data model

```
categories (tree, polymorphic pivot via categorizables)
tags       (flat, polymorphic pivot via taggables)
products   ← belongsToMany → packages  (via package_product)
products   ← belongsToMany → ingredients (via ingredient_product, potency pivot)
products   → hasMany → product_coas
products   → belongsTo → product_classes / product_types / product_forms /
             administration_methods / measurement_units (volume_unit_id)
packages   → hasMany → plans
products   → hasMany → plans   (term plans — package_id XOR product_id, model-guarded)
products & packages → morphMany → catalog_relations (source) → morphTo related
```

All catalog items share `CatalogStatus` (Pending / Draft / Published / Archived) and `SortableTrait` (`position` column). Only Published items are served via the public API.

### Lookup tables (admin-managed vocabulary)

Six lookup modules under **Shop** in Filament. All are local rows (NOT enums) so
non-PRX fulfillment deployments can manage their own vocabulary; each carries a
provider mapping column so PRX syncs land with matching terminology:

| Table | Provider mapping | Seeded by |
|---|---|---|
| `product_classes` | `provider_product_class_id` (uuid) | PRX sync (`/product-classes` or embedded objects) |
| `product_types` | `provider_product_type_id` (uuid), `product_class_id` FK | PRX sync |
| `ingredients` | `provider_ingredient_id` (uuid) | PRX sync (product detail payload) |
| `administration_methods` | `provider_value` (PRX `ProductDeliveryMethod` int) | `CatalogVocabularySeeder` (16 rows) |
| `product_forms` | `provider_value` (PRX `ProductForm` int) + `requires_volume` flag | `CatalogVocabularySeeder` (25 rows) |
| `measurement_units` | `provider_value` (PRX `UnitsOfMeasure` int) | `CatalogVocabularySeeder` (10 rows) |

`CatalogVocabularySeeder` runs from `DatabaseSeeder`, is idempotent (slug/abbr
match), and never overwrites admin-edited names.

### Key columns

| Table | Notable columns |
|---|---|
| `products` | `provider_product_id`, `provider_product_sku`, `provider_encounter_type_id`, `highlights` (json), `detail_sections` (json), `badge_text`, classification FKs (`product_class_id`, `product_type_id`, `product_form_id`, `administration_method_id`), `volume` dec(10,4) + `volume_unit_id`, `inventory_status` (enum, drives `is_in_stock` via saving hook — InStock/BackOrdered = purchasable), `is_controlled_substance`, `rx_required`, `cost` dec(10,2) **internal-only** |
| `packages` | same provider columns, `banner_image_path`, `highlights` (json), `detail_sections` (json), `badge_text`, `cost` |
| `plans` | `billing_period` (enum), `billing_mode` (enum, mirrors PRX BillingMode: prepaid_term/recurring/installment/external), `term_months` (int), `is_recurring`, `rebill_strategy` (enum), `trial_days`, `is_default`, `intro_price`, `cost`, `provider_product_ids` (json) |
| `categories` | `provider_encounter_type_id` — maps a category to a PRX encounter type for dynamic intake |
| `package_product` | `sort_order`, `is_included` (true = bundled, false = optional add-on) |
| `ingredient_product` | `concentration` dec(10,4) + `concentration_unit_id`, optional `per_volume` dec(10,4) + `per_volume_unit_id` (nullable for lyophilized), `provider_quantity_label` (raw PRX string), `position`. Custom pivot `IngredientProduct` with `potencyLabel()` → "50 mg" / "10 mg / 3 ml" |
| `product_coas` | `batch_number` (unique per product), `file_path` (PDF or image on public disk, `catalog/coas/`), `file_type` (derived on save), `issued_at`, `is_visible`, `created_by` |
| `catalog_relations` | double-polymorphic `source` + `related` morphs (Product/Package both sides), `relation_type` (`related` \| `pairs_with`), `position`. `HasCatalogRelations` trait → `relatedItems()` / `pairsWithItems()` (published only) |

**`cost` is internal P&L data and must never be serialized by any public API
resource** — regression-tested in `ProductClassificationTest`.

### Highlights format

The Filament Repeater stores highlights as `[{"item":"First point"}, {"item":"Second point"}]`. The API Resources normalize this to `["First point", "Second point"]` via `collect()->pluck('item')`.

### Provider encounter type resolution (at intake time)

Priority order when determining which encounter type to use:
1. `products.provider_encounter_type_id` (product-level override)
2. `categories.provider_encounter_type_id` of the product's primary category
3. Fallback: prompt user to pick from active encounter types

---

## API endpoints

All catalog endpoints are **public** (no auth required). Rate-limited to 120 req/min per IP.

### `GET /api/v1/catalog/categories`

Returns all visible categories ordered by `position`.

| Param | Type | Description |
|---|---|---|
| `tree` | bool | Embed children. Returns top-level only when true. |

```json
{
  "data": [
    {
      "id": 1,
      "name": "Hormone Therapy",
      "slug": "hormone-therapy",
      "provider_encounter_type_id": "hrt-basic",
      "children": [...]
    }
  ]
}
```

### `GET /api/v1/catalog/categories/{slug}`

Single category with `parent` and `children`.

### `GET /api/v1/catalog/products`

Paginated. 15 per page, max 50.

| Param | Type | Description |
|---|---|---|
| `category` | string | Filter by category slug |
| `tag` | string | Filter by tag slug |
| `class` | string | Filter by product class slug |
| `type` | string | Filter by product type slug |
| `form` | string | Filter by product form slug |
| `ingredient` | string | Filter by ingredient (compound) slug |
| `featured` | bool | Featured products only |
| `in_stock` | bool | In-stock products only |
| `price_min` / `price_max` | float | Effective-price bounds |
| `search` | string | Name / subtitle LIKE search |
| `sort` | string | `position` (default) \| `name` \| `-name` \| `price` \| `-price` \| `newest` \| `oldest` — whitelisted in `SortsCatalogQueries`; price sorts on `COALESCE(sale_price, retail_price)` |
| `per_page` | int | Page size (max 50) |

Response includes `links` + `meta` pagination keys. Cards carry a
`classification` block (`class`/`type`/`form`/`administration_method`, each
`{id, name, slug}` or null), `volume {value, unit}`, `inventory_status`,
`rx_required`, `is_controlled_substance`.

### `GET /api/v1/catalog/products/{slug}`

Full product detail. Adds over the card shape: `seo`, `ingredients`
(`[{name, slug, concentration, per_volume, label}]` — `label` is the composed
potency e.g. `"10 mg / 3 ml"`), `coas` (visible only —
`[{batch_number, file_url, file_type, issued_at}]`), `detail_sections`
(`[{title, placement: accordion|tab, content}]`), `related` and `pairs_with`
(published-only light cards with `type: product|package` for routing), and
`plans` (published product term plans, position-ordered — same PlanResource
shape as package plans; drives the detail-page deal grid, with the product's
own `price` as the one-time/buy-once option). A plan belongs to a package OR
a product, never both. Relation light cards price packages from their
default plan; `price.effective` is `null` (never `0.00`) when unpriced.
Also includes `faqs` — see [Polymorphic FAQs](#polymorphic-faqs).

### `GET /api/v1/catalog/packages`

Same filter params as products (minus class/type/form/ingredient) plus `sort`. Each package includes its Published plans and a `price_range` computed from plan prices.

### `GET /api/v1/catalog/packages/{slug}`

Full package detail. Includes `products` (bundled items), `plans` (subscription tiers), `detail_sections`, `related`, `pairs_with`, `faqs`. Plans include a `billing` sub-object with `term_months`, `is_recurring`, `rebill_strategy`, `trial_days`, and `mode`/`mode_label` (billing mode).

### Polymorphic FAQs

Products and packages expose `faqs` on their **show** endpoints only:
`[{id, question, answer, category}]` (`category` is the FAQ category name or
`null`). Items come from the Content module's `faq_items` table via the
`faqables` morph pivot (`App\Models\Concerns\HasFaqs` on Product/Package;
inverse `products()`/`packages()` on `FaqItem`).

- **Ordering** is per-attachment: `faqables.position` (drag-reorder in the
  admin), NOT `faq_items.position` (which orders the general FAQ page).
  Both tables have a `position` column — always qualify the pivot column.
- **Visibility**: only `is_published` items are returned; unpublished items
  stay attached but never render. Attachment does not affect the general
  `/api/v1/faqs` endpoint — the same item can serve both.
- **Admin**: shared `FaqsRelationManager`
  (`app/Filament/Resources/Catalog/Products/RelationManagers/`, wired into
  both ProductResource and PackageResource) — attach existing items
  (multi-select) or author a new one in place ("New FAQ" creates the
  FaqItem and attaches it).

### Injectable sections + detail_layout (per-record page building)

Products and packages expose on their **show** endpoints:

- `sections` — ordered list of CMS **section envelopes**
  (`{type, origin, anchor, global, data, schema?}`), the exact contract
  `/api/v1/pages` uses, so the frontend `SectionRenderer` consumes them
  unchanged. Backing: `catalog_item_sections` morph table
  (`App\Models\Catalog\CatalogItemSection` — PageSection's morph-attached
  sibling; `HasItemSections::sections()` on Product/Package). Serialized by
  the same `SectionDataTransformer` (media resolution, catalog inlining,
  SVG sanitizing, global-block indirection all apply). Every registered
  section type — code blueprints (video-embed, image-text-split, …) AND
  admin-defined flexible types — is available per record. Global blocks
  compose identically to pages: attach one block to many records, edit
  once. Disabled sections and unresolvable types are skipped.
- `detail_layout` — nullable per-record presentation JSON, passed through
  verbatim to the frontend's `normalizePresentation`:
  `{template: classic|conversion, accordions: {placement: side|below},
  pair_with: {desktop: 1–4, mobile: 1–2}, rails: [related|stacks|associated]}`.
  Every key optional; missing = deployment default. Never invent keys
  backend-side — the frontend normalizer owns defaults.

Admin: shared `SectionsRelationManager` ("Page Sections" tab, drag-ordered,
same form builder as the page builder — the statePath/`$get('type')`
gotchas from feedback-filament-group-get-paths apply) plus a "Detail page
layout" section on the record form (dotted `detail_layout.*` field names,
no statePath). Catalog show endpoints are not CmsCache-cached, so no
observer wiring is needed.

### Reviews (base module)

Products and packages expose on their **show** endpoints only:

- `rating` — `{average, count}` across approved reviews, or `null` when none
  (the frontend must render nothing, never a zero-count rating).
- `reviews` — approved only, newest `reviewed_at` first:
  `[{id, rating, author_name, title, body, reviewed_at}]`.

Backing: polymorphic `reviews` table (`App\Models\Content\Review`,
`HasReviews` concern on Product/Package with `approvedReviews()` +
`ratingSummary()`). Deliberately thin — rows are admin-curated today
(`source` = `admin`); the patient portal and per-client external review
integrations are expected to write into the same table with their own
`source` values, and the moderation flow (`is_approved`) stays identical.
Admin: shared `ReviewsRelationManager` on both catalog resources
(author-in-place, approve toggle, ternary approved filter).

### `GET /api/v1/catalog/tags`

All visible tags ordered by position.

### `GET /api/v1/catalog/facets`

Filter-sidebar payload: `categories` / `classes` / `types` / `forms` /
`ingredients` / `tags` (each `[{name, slug, count}]`, published-product counts,
zero-count rows omitted), `price {min, max, currency}` bounds across published
products, `availability {in_stock, out_of_stock}` counts.

---

## Controllers

| Controller | File |
|---|---|
| ProductController | `app/Http/Controllers/Api/V1/Catalog/ProductController.php` |
| PackageController | `app/Http/Controllers/Api/V1/Catalog/PackageController.php` |
| CategoryController | `app/Http/Controllers/Api/V1/Catalog/CategoryController.php` |
| TagController | `app/Http/Controllers/Api/V1/Catalog/TagController.php` |

All extend `ApiController`. List endpoints return `ResourceClass::collection()` (Laravel auto-adds pagination links when passed a paginator). Show endpoints return `$this->success($resource->toArray(request()))`.

## Resources

| Resource | File |
|---|---|
| ProductResource | `app/Http/Resources/Api/V1/Catalog/ProductResource.php` |
| PackageResource | `app/Http/Resources/Api/V1/Catalog/PackageResource.php` |
| PlanResource | `app/Http/Resources/Api/V1/Catalog/PlanResource.php` |
| CategoryResource | `app/Http/Resources/Api/V1/Catalog/CategoryResource.php` |
| TagResource | `app/Http/Resources/Api/V1/Catalog/TagResource.php` |

`seo` is gated with `$this->when($request->routeIs('...show'), ...)` — only included on detail pages, not list responses.

## PRX sync & mapping

### `SyncPrescribeRxCatalogAction` (`app/Actions/Catalog/`)

Triggered from ListProducts/ListPackages header actions, the PRX Catalog page,
or `php artisan prescribe-rx:sync-catalog`. Per run:

1. Upserts `product_classes`/`product_types` from `/product-classes` +
   `/product-types` (tolerates 404 on older PRX deployments — the embedded
   `product_class`/`product_type` objects on each product cover the gap).
2. Products/packages/plans matched by `provider_*_id`.
   - **Pending rows**: everything updated (name, descriptions, flags).
   - **Curated rows** (Draft/Published/Archived): marketing content preserved.
   - **Provider-truth fields update on EVERY sync regardless of status**:
     classification FKs, `rx_required`, `pricing.cost`, ingredients, plan
     `billing_mode`, pricing, SKU, fulfillment center.
3. Ingredients come from the product detail payload (`GET /products/{id}` when
   the list omits them). Upsert by provider uuid with case-insensitive name
   fallback (backfills the provider id onto admin-created rows). Quantity
   strings (`"50mg"`, `"10 mg / 3 ml"`) are parsed into the potency pivot;
   the raw string is always kept in `provider_quantity_label`.

### Mapping admin

- **PRX Catalog page** (`app/Filament/Pages/PrxCatalog.php`, Shop → PRX
  Catalog): Filament custom-data table over `RemoteCatalog`
  (`app/Services/PrescribeRx/RemoteCatalog.php`, 15-min cache). Kind filter
  (products/packages), search, mapped/unmapped badges. Row actions: **Import**
  (Pending mapped shell via `ImportPrxCatalogItemAction`; enriched on next full
  sync), **Map to existing** (unmapped local rows only,
  `MapProviderCatalogItemAction`), **Open local**. Header: refresh cache, run
  full sync.
- **Products/Packages tables**: `MatchToPrxTableAction` — suggestion-ranked
  select (exact SKU = 100, else `similar_text` name score; ≥60% top match
  preselected) + "Clear PRX mapping". List pages have **Unmapped** review tabs.

## Tests

`tests/Feature/Api/V1/Catalog/` — endpoint coverage: status filtering, category/tag/featured/search filters, class/type/form/ingredient filters, sort whitelist, facets, highlights normalization, price_range computation, per_page cap, plan billing fields, package-product relationship, classification/ingredients/COA/detail_sections exposure, cost-never-leaked regression, related/pairs_with published-only.
`tests/Feature/Catalog/` — `SyncPrescribeRxCatalogTest` (mocked Client: classification upsert, ingredient parsing, curated-content preservation, endpoint-404 tolerance), `PrxMappingTest` (suggestion scoring, map/unmap, import shells, page render), `ProductActionFieldsTest` (DTO→Action full field round-trip regression).

---

## Sex & age eligibility

Added 2026-08-28. The gate the recommendation chain applies **before** ranking.

### Schema

`ingredients` gains four columns (`2026_08_28_090000_add_eligibility_to_ingredients_table`):

| Column | Type | Notes |
|---|---|---|
| `sex_eligibility` | `string(16)`, default `any`, indexed | Cast to `App\Enums\Catalog\SexEligibility` (`any\|male\|female`) |
| `min_age` / `max_age` | `unsignedTinyInteger` nullable | Null = unbounded on that side. **Null is not 18** |
| `eligibility_note` | `text` nullable | Operator-authored rationale, quoted in the protocol/PDF |

`leads` gains `age` (`unsignedTinyInteger`, nullable). It coexists with `date_of_birth` rather
than replacing it: the quiz asks an age, a clinical intake captures a birth date, and
back-computing one from the other would fabricate a birthday nobody gave us.
`Lead::effectiveAge()` encodes the precedence (`date_of_birth` wins).

### Why the ingredient and not the product

The same argument the health-goals migration makes for recommendations. An ingredient is what a
product *contains*, and one ingredient backs several SKUs. Stated on the substance the rule is
written once and inherited by products that do not exist yet; stated on the product it is
restated per SKU and drifts the first time a new testosterone item ships with the flag
forgotten.

Measured before choosing: 10 of 11 products had ingredients attached. The eleventh was
`testosterone-cypionate`, whose pivot row was simply missing — a data gap, not a case against.

**There is deliberately no product-level override column.** `health_goal_product` has 0 rows,
so there is no demand, and a second place to state one clinical fact fails silently when the two
disagree. If a combination product ever needs looser eligibility than its strictest ingredient,
that is one migration adding an explicit nullable column where null keeps meaning "derive".

### Resolution

`App\Services\Recommendations\GoalRecommendationResolver`, with
`VisitorProfile(?sex, ?age)` as the input.

| Method | Reading | Use |
|---|---|---|
| `ingredientsFor` | Eligible only, ranked `is_first_line` then `relevance_weight` | The first hop |
| `productsFor` | Permissive — surfaces on ANY eligible ingredient | Browsing surfaces |
| `strictProductsFor` | Conservative — EVERY ingredient must pass | Anything reading as advice |
| `productIsSafe` | Safety only, goal-independent | Stack membership |
| `packagesFor` | ≥1 relevant product, ALL products safe | Stacks |
| `resolve` | All of the above plus `mapped_count`, `excluded_count` and `outcome` | The endpoint |

Three rules that are load-bearing:

- **A null answer is permissive.** `null` means "not asked", not "answered nothing". A visitor
  who never took the quiz sees the whole shelf. Narrowing on an absent answer would hide
  products from people who told us nothing, and nobody would notice.
- **A product with no ingredients is ineligible, not unrestricted.** It cannot be reached
  through the chain anyway, so saying it costs nothing and closes the bypass.
- **Safety ≠ relevance.** A stack is judged relevant by one product and safe by all of them.
  Conflating the two rejected every package in the catalogue — see the regression test.

### Endpoint

`POST /api/v1/protocol/preview` — `{goals: string[], sex?: string, age?: int}`.

**POST, not GET, deliberately.** A GET would write
`?goal=sexual-wellness&sex=male&age=62` into every access and proxy log — a health inference
about an IP. The response is per-visitor and uncacheable, so GET buys nothing either.

**Stores nothing.** Answers become a record only at lead submission, a separate consented step.

Each goal returns an `outcome` naming the three states the funnel must tell apart:

| `outcome` | Meaning | Frontend copy |
|---|---|---|
| `matched` | We have something | The products/stacks |
| `restricted` | We had something; not for this visitor | "we don't currently stock something appropriate" |
| `unmapped` | Nobody has built this goal out | "we're still building out our options" |

**`outcome` cannot be derived from `excluded_count`.** That counts INGREDIENT-level exclusions
only, and a goal can restrict at the PRODUCT level instead: map a unisex ingredient A, stock one
product holding both A and male-only B, and a female visitor gets an eligible ingredient, an
`excluded_count` of 0, and no products. So `resolve()` compares against an **unfiltered
baseline** — what this goal would offer someone we know nothing about. Empty baseline means
nobody built it (`unmapped`); non-empty baseline with an empty result means this visitor was
filtered out (`restricted`). The extra resolve runs only when the result is already empty.

`excluded_count` is a **count, not a list** — returning the names would let anyone enumerate
which substances are gated by varying the request body.

### Tests

`tests/Feature/Recommendations/` — 17 tests.
`GoalRecommendationResolverTest` covers the gate; `ProtocolPreviewEndpointTest` covers the HTTP
layer and exists because the resolver tests all passed while the endpoint 500'd: the resolver
returned `collect()` (a `Support\Collection`, no `->loadMissing()`) from its empty paths, so the
controller threw on the first genuinely `restricted` result — the exact outcome the feature was
built to produce. Frontend smoke check: `atlas-protocol-web/scripts/quiz-flow-check.mjs`.
