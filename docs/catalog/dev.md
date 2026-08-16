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
(published-only light cards with `type: product|package` for routing).

### `GET /api/v1/catalog/packages`

Same filter params as products (minus class/type/form/ingredient) plus `sort`. Each package includes its Published plans and a `price_range` computed from plan prices.

### `GET /api/v1/catalog/packages/{slug}`

Full package detail. Includes `products` (bundled items), `plans` (subscription tiers), `detail_sections`, `related`, `pairs_with`. Plans include a `billing` sub-object with `term_months`, `is_recurring`, `rebill_strategy`, `trial_days`, and `mode`/`mode_label` (billing mode).

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
