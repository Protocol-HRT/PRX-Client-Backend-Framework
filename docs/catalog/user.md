# Catalog Module — Operator Guide

## Overview

The Catalog is where you manage the products, packages, subscription plans, categories, and tags that appear on your storefront. All catalog data is served to the React frontend via the API — nothing is hard-coded.

---

## Products

A **Product** is a single item that can be added to a cart independently (e.g., a single vial, a cream, a supplement).

**Key fields:**

| Field | Description |
|---|---|
| Name / Slug | Slug is the URL path (`/products/{slug}`). Auto-generated from name. |
| Subtitle | Short one-liner shown under the name in listing cards. |
| Short description | 1-2 sentences for listing pages and meta fallback. |
| Description | Full rich text for the product detail page. |
| Status | **Draft** = hidden. **Published** = live. **Archived** = discontinued. |
| Badge | Small label shown on listing cards ("Best Seller", "New", "Rx Required"). |
| Highlights | Bullet points for the detail page feature list. Add one per row. |
| Pricing | **Retail** = regular price. **Sale** = discounted price (shown if set). **Suffix** = text after price ("/vial", "/month"). |
| Hero image | Main product image. |
| Gallery | Additional images for the image carousel. |
| Featured | Surfaces product in "Featured" filtered views. |
| Requires lab | Shows a "Lab work required" badge on the detail page. |
| Provider product ID | The matching product UUID in PrescribeRx (or other configured provider). Required for checkout. |
| Provider SKU | Human-friendly product code from the provider. |
| Provider encounter type ID | Overrides the category-level encounter type for this specific product's intake form. Leave blank to inherit from category. |
| Classification | Product class (Peptides, HRT…), type, physical form (vial, troche…), and administration method (oral, sub-q injection…). Options are managed under Shop → Product Classes / Types / Forms / Administration Methods. |
| Volume + unit | Container size, e.g. `10 mg` for a lyophilized vial or `3 ml` for a liquid. |
| Inventory status | In Stock / Back Ordered / Out of Stock / Discontinued. When set, the In-stock flag is derived automatically (In Stock and Back Ordered count as purchasable). Leave empty to manage the flag by hand. |
| Prescription required | Mirrors the provider's Rx flag; kept current by sync. |
| Controlled substance | Compliance flag for reporting. |
| Cost | What the company pays — used for internal reporting/P&L only. **Never shown on the storefront or the public API.** |
| Detail page content | Reorderable content blocks for the detail page. Each block has a title, copy, and a placement: "Sidebar accordion" (e.g. How To Use) or "Description tab". |

**Related tabs on the product edit page:**

- **Ingredients** — attach ingredients with potency: concentration + unit
  (e.g. `50 mg`), and for liquids an optional per-volume denominator
  (`10 mg / 3 ml`). Leave per-volume blank for lyophilized/dry products.
  Ingredient rows themselves are managed under Shop → Ingredients.
- **Certificates of Analysis** — upload a COA per manufacturing batch
  (PDF or image, max 10 MB) with the batch number and issue date. Visible
  COAs are listed on the public product detail API.
- **Related & Pairs With** — link other products *or* stacks. "Related" =
  similar items; "Pairs With" = suggested companions for building a custom
  stack. Both power the corresponding sections on detail pages.

### Vocabulary lookups (Shop menu)

Product Classes, Product Types, Ingredients, Administration Methods, Product
Forms, and Measurement Units are all editable lists — rename, reorder,
deactivate, or add rows freely. A fresh install seeds standard clinical
vocabulary (16 administration methods, 25 forms, 10 units) matching PrescribeRx
terminology. The "Provider mapping" fields on each row tie it to the PRX
equivalent so synced products reuse your rows instead of creating duplicates —
leave them blank for vocabulary you add for other fulfillment sources.

### PRX matching & mapping

- **Shop → PRX Catalog** — browse the live PRX inventory. Unmapped rows can be
  **Imported** (creates a local Pending item linked to the PRX row) or
  **Mapped to an existing** local item. Mapped rows link straight to the local
  edit page. "Run full sync" imports everything and refreshes pricing/clinical
  data on all mapped rows (your curated names, descriptions, and images are
  never overwritten once an item leaves Pending).
- **Products / Packages lists** — the **Unmapped** tab shows local items not
  yet linked to PRX. Use **Match to PRX** on a row for ranked suggestions
  (best SKU/name matches are starred); **Clear PRX mapping** unlinks a row.

---

## Packages

A **Package** bundles one or more products together and is sold via **Plans** (pricing tiers with terms).

**Key fields** (same as Product, plus):

| Field | Description |
|---|---|
| Banner image | Wide hero banner for the package landing section. |
| Products | Assign which products are included in this package. Set sort order in admin. |
| Plans | Add subscription tiers (see Plans section below). |

---

## Plans

A **Plan** belongs to a Package **or a Product** (never both) and defines a
pricing tier and billing cadence. Products get the same Plans tab as
packages — add term plans (e.g. 3/6/12-month pricing) to sell a single
product on subscription; a plan with "Recurring / subscription" enabled IS
the subscription, no separate toggle needed. The product's own retail/sale
price remains the one-time "buy once" option shown alongside the plan grid.

**Example:** A "Testosterone" package might have three plans:
- Monthly — $299/mo, auto-renews
- 3-Month Supply — $799 (save 11%), recurring every 3 months
- 6-Month Supply — $1,399 (save 22%), recurring every 6 months

| Field | Description |
|---|---|
| Billing period | Monthly / Quarterly / 9-Month / Annual / One-time |
| Term (months) | Explicit month count sent to the provider at checkout (1, 3, 6, 9, 12). |
| Retail / Sale price | Display prices. Sale price shows as discounted if set. |
| Price suffix | Appended to price in the UI ("/mo", "every 3 months"). Auto-filled from billing period if blank. |
| Badge | "Most Popular", "Best Value", etc. |
| Pre-selected | Mark one plan per package as the default selection on the package page. |
| Recurring | Toggle ON for subscription plans. OFF = one-time purchase. |
| Rebill strategy | **Auto-renew** = renews on schedule until cancelled. **Patient choice** = patient picks interval at checkout. |
| Trial days | Number of free trial days before first charge (0 = no trial). |
| Provider plan ID | Matching plan UUID in the configured provider. |
| Provider product IDs | JSON array of provider product UUIDs to pass at intake for this plan's line items. |

---

## Categories

Categories group products and packages for storefront navigation. They support one level of nesting (parent → children).

**Provider encounter type ID:** The most important field for clinical workflows. When set on a category, all products in that category will use this encounter type's intake form at checkout. Override at the product level when a specific product needs a different intake form.

---

## Tags

Tags are a flat taxonomy for cross-cutting filters (e.g., "Popular", "Testosterone", "Weight Loss"). Tags can be applied to products, packages, and plans.

---

## Product & stack FAQs

Every product and package edit page has a **FAQs** tab. Questions shown there
render on that item's storefront detail page, in the order you drag them into.

- **Attach** picks existing questions from the FAQ module (multi-select) — the
  same question can appear on any number of products and on the general FAQ
  page at once.
- **New FAQ** authors a question in place; it is saved into the FAQ module and
  attached to this item in one step.
- Unpublishing a question (here or in the FAQ module) removes it from every
  page it's attached to without losing the attachments.
- The drag order here only affects this item's page. The general FAQ page
  keeps its own category/position ordering.

---

## Publishing checklist

Before setting a product or package to **Published**:
- [ ] Hero image uploaded
- [ ] Short description filled in
- [ ] At least one category assigned
- [ ] Provider product/package ID set (required for checkout to work)
- [ ] If a package: at least one Published plan with a price

---

## Display pricing vs transaction pricing

Prices in this admin are **display prices** — what customers see on the storefront. The actual transaction amount is determined by the provider (PrescribeRx) at checkout time based on the `provider_product_ids` and `provider_plan_id` you configure on each plan. If local checkout via NMI/Authorize.net is configured instead, the `sale_price ?? retail_price` is used as the transaction amount.
