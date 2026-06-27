# Catalog Module — Developer Guide

**Status:** Shipped 2026-06-22

## Data model

```
categories (tree, polymorphic pivot via categorizables)
tags       (flat, polymorphic pivot via taggables)
products   ← belongsToMany → packages  (via package_product)
packages   → hasMany → plans
```

All catalog items share `CatalogStatus` (Draft / Published / Archived) and `SortableTrait` (`position` column). Only Published items are served via the public API.

### Key columns

| Table | Notable columns |
|---|---|
| `products` | `provider_product_id`, `provider_product_sku`, `provider_encounter_type_id`, `highlights` (json), `badge_text` |
| `packages` | same provider columns, `banner_image_path`, `highlights` (json), `badge_text` |
| `plans` | `billing_period` (enum), `term_months` (int), `is_recurring`, `rebill_strategy` (enum), `trial_days`, `is_default`, `provider_product_ids` (json) |
| `categories` | `provider_encounter_type_id` — maps a category to a PRX encounter type for dynamic intake |
| `package_product` | `sort_order`, `is_included` (true = bundled, false = optional add-on) |

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
| `featured` | bool | Featured products only |
| `search` | string | Name / subtitle LIKE search |
| `per_page` | int | Page size (max 50) |

Response includes `links` + `meta` pagination keys.

### `GET /api/v1/catalog/products/{slug}`

Full product detail. Includes `seo` object (meta_title, meta_description, og_image_url). Highlights normalized to `["text"]`.

### `GET /api/v1/catalog/packages`

Same filter params as products. Each package includes its Published plans and a `price_range` computed from plan prices.

### `GET /api/v1/catalog/packages/{slug}`

Full package detail. Includes `products` (bundled items) and `plans` (subscription tiers). Plans include a `billing` sub-object with `term_months`, `is_recurring`, `rebill_strategy`, `trial_days`.

### `GET /api/v1/catalog/tags`

All visible tags ordered by position.

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

## Tests

`tests/Feature/Api/V1/Catalog/` — 25 assertions covering: status filtering, category/tag/featured/search filters, highlights normalization, price_range computation, per_page cap, plan billing fields, package-product relationship.
