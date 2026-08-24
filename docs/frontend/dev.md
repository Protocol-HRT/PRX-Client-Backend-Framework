# Frontend Implementation Guide (for a new company / deployment)

Status: current as of 2026-08-15

This guide explains how a company deploying its own prx-backend instance builds (or commissions) the public-facing frontend. The backend is **headless**: it serves a versioned REST API (`/api/v1`) plus the Filament admin panel. The frontend is a **separate application in its own repository, running on its own origin** — typically the company's apex domain (`www.company.com`) with the backend on a subdomain (`api.company.com` or `admin.company.com`). It is *not* a path under the Laravel app.

Any stack that can call HTTP works. The reference skeleton is Next.js (App Router, React Server Components) — see the companion frontend repo's `README.md` and `lib/api.js`.

## 1. Core contract

- **Base URL**: `https://<backend-host>/api/v1`
- **Envelope**: success → `{ data, meta?, message? }`; failure → `{ message, errors? }`. Always unwrap `data`.
- **Versioning**: breaking changes bump the prefix to `/v2`. Pin to `v1`.
- **Live reference**: interactive OpenAPI docs at `https://<backend-host>/api/docs` (Scalar UI, generated from code).
- **Caching**: content endpoints are server-cached (~300s) and invalidated on admin edits. Frontends should still cache/ISR on their side (the reference skeleton uses `revalidate: 300`).

## 2. Authentication & origins

Public content reads (config, pages, layout, menus, catalog, blog, FAQ, profiles) require **no token**.

For production installs, issue an **ApiClient token** in the admin (API Clients section) and send it as `Authorization: Bearer <token>`. When an ApiClient has an `allowed_origins` list, any request bearing its token must also carry a matching `Origin` header — this is how an install pins its API to its own frontends. Patient-portal endpoints (`/patient/*`) use their own patient token flow; carts use an anonymous `X-Cart-Token` (below).

Keep the token server-side (env var). Never ship it in client-side JavaScript.

## 3. Boot: one config call drives all branding

`GET /api/v1/config` (cached 5 min) returns everything install-specific:

| Key | Contents | Frontend responsibility |
|---|---|---|
| `brand` | name, tagline, logo variants, favicon, hero image, announcement | Render chrome; never hardcode a brand string or logo file |
| `theme` | `primary_color`, `accent_color`, `accent_secondary_color`, `background_color`, `text_color`, `font_display`, `font_body`, `custom_css`, `frontend_template`, `text_classes` | Map colors/fonts to CSS custom properties on `<html>`; inject `custom_css` **after** your own styles; switch component/layout variants on `frontend_template` |
| `contact` | emails, phone, address, business hours, social links | Contact page, footer, JSON-LD |
| `seo` | default title/description, OG image, `google_analytics_id`, `google_tag_manager_id`, `facebook_pixel_id`, `tiktok_pixel_id`, `custom_head_scripts`, `custom_body_scripts`, `allow_indexing` | Metadata defaults, robots handling, analytics bootstrapping. Inject the custom script fields verbatim (head / end-of-body) |
| `provider` | telehealth provider name/slug, `supports_embed`, `supports_patient_portal_auth` | Feature-gate checkout embed and patient portal |

**Trust note on `custom_css` / `custom_*_scripts`:** these execute verbatim in the page. They are writable only by the install's permission-gated admins and served from the install's own backend — the same trust level as the frontend deploy itself. Never point a frontend at a backend you don't control.

**Text color classes:** `theme.text_classes` is a list of `{name, color}` rows (managed in Settings → Theme). The frontend emits a `.tx-{name} { color: … }` rule for each, so admins can color runs of rich text with `<span class="tx-gold">…</span>` without touching CSS.

**Section presentation:** every section's `data` may carry `extra_padding` (`sm`|`md`|`lg`) — a shared knob added by the section editor; the renderer wraps the section to add vertical breathing room. Long-copy fields (hero slide `description`, image-callout `content`, timeline `body`, hero-banner `subhead`) are HTML: render them with the shared `Html` component (plain legacy text passes through with newlines converted to `<br />`).

**Theming model:** the frontend defines *structure* with neutral CSS variables (`--color-primary`, `--font-display`, …); the backend supplies *values*. Per-install visual identity therefore requires zero frontend code changes: colors/fonts via theme settings, arbitrary overrides via `custom_css`, and wholesale layout swaps via `frontend_template` (the frontend maps each supported template slug to its own component set; unknown slugs should fall back to `default`).

## 4. Pages & sections (CMS)

- `GET /pages` — published page index (no sections).
- `GET /pages/{slug}` — `{ title, slug, title_banner, seo, sections: [envelope…] }`.

Each section **envelope** is `{ type, origin, anchor, global, has_content, data, schema? }`:

- `origin: "code"` — one of the built-in blueprint types (hero, faq, testimonials, product-slider, …). Render with a dedicated component per `type`. Product/package types arrive with full catalog card data already inlined in `data`.
- `origin: "flexible"` — an admin-defined type. `schema` is a field-kind map (`text`, `richtext`, `image`, `link`, `boolean`, `select`, `svg`, `repeater`, `products`, `packages`) — render generically from it.
- **`has_content: bool` — render nothing when this is `false`.** A section an editor added but never filled in still carries its blueprint's structural flags (`theme: "light"`, `alignment: "left"`, `mode: "manual"`), so a naive "is every value empty?" check judges it authored and an empty scaffold reaches the live page. The backend knows which of its own keys are presentation and does that classification for you — it is computed after catalog inlining, so a slider whose query returned nothing is correctly `false`. Do not reimplement this by guessing which keys look like flags.
- `anchor` → element `id`; `type` / `global.slug` → CSS hooks (`section--{slug}`); `global` marks shared blocks.
- Image kinds arrive resolved as `{ id, url, alt, width, height }`; SVG fields arrive sanitized; unknown types should render a visible placeholder in dev builds, never crash.

### 4a. Authored copy is HTML — never render it as text

Every text field an operator can type into is authored in a rich editor and
arrives as an **HTML string**. Rendering one as a plain text node escapes the
markup and prints the tags on the page (`The Operating System&lt;br /&gt;for
Longevity`). This is the single most common integration bug against this API.

Fields come in two kinds, and the kind tells you what markup you may receive:

| Kind | Example fields | You will receive | Render it as |
|---|---|---|---|
| **inline** | `heading`, `headline`, `eyebrow`, `emphasis`, `title`, `label`, `value`, `meta`, `caption`, `badge`, `q`, `name`, `quote`, `text` | Inline markup only — `<b> <strong> <i> <em> <u> <s> <a> <br> <span> <sup> <sub> <small> <code> <mark>` | Inject into an element **you** choose (`<h1>`, `<h2>`, `<li>`, `<span>`) |
| **prose** | `body`, `content`, `description`, `bio`, `a` (FAQ answer) | Block markup — paragraphs, `<h2>`/`<h3>`, `<ul>`/`<ol>`, `<blockquote>`, plus all inline tags | Inject into a container of its own (a `<div>`) |

**The inline guarantee is load-bearing.** The backend strips block markup from
inline fields on save, so you can safely put the value inside a heading you
picked yourself without risking `<h1><h2>…</h2></h1>` and a corrupted document
outline. In exchange, do not wrap a *prose* value in a `<p>` or `<h2>` — it
already carries its own blocks, and nesting them is invalid.

Normalization happens on write (`App\Cms\Support\HtmlCopy`), so the payload is
already in the promised shape — a frontend does not need to sanitize or unwrap.
Two details worth handling anyway:

- **Legacy plain text.** Values authored before a field became a rich input are
  stored as plain text with real newlines. Convert `\n` → `<br />` when the
  value contains no markup, so those keep their line breaks.
- **Empty means empty.** A field carrying only empty markup normalizes to
  `null`, so a null check is enough — you will not receive `<p></p>`.

Trust model: this is permission-gated admin HTML from the install's own
backend, the same path as `custom_head_scripts`. Inject it directly. Never
route user-generated content through the same path.

Route pattern: a catch-all route mapping URL path → page slug, plus `/` → slug `home`. Per-page `seo` overrides the config defaults; respect `noindex`.

- `GET /layout` — six fixed regions (`top_bar`, `header`, `pre_footer`, `footer`, `sidebar_left`, `sidebar_right`; keys always present). Items are `{kind: "section"|"menu", …}` — sections use the same envelope; menus embed a tree.
- `GET /menus/{slug}` — menu tree. Entity links emit `{type, slug}` (`page`, `product`, `package`, `catalog_category`, `blog_post`, `blog_category`); **the frontend owns the route patterns** (e.g. `product` → `/products/{slug}`). `url`/`anchor` links emit `{type, url}`. Unpublished targets are already dropped server-side.

## 5. Datasets

| Endpoint | Notes |
|---|---|
| `GET /catalog/products` (+`/{slug}`) | Paginated; filters: `category`, `tag`, `search`, `price_min/max`, `featured`, `in_stock`, `per_page`. Prices as `{retail, sale, effective, suffix, currency}` |
| `GET /catalog/packages` (+`/{slug}`) | Packages with member products and `plans` (billing period, term, recurring flag, trial) — model "first month vs recurring" offers from plans |
| `GET /catalog/categories`, `/tags` | Taxonomy for navigation and filter facets |
| `GET /blog/posts` (+`/{slug}`), `/blog/categories`, `/blog/tags` | `content` only on show route |
| `GET /faq`, `/faq/categories` (+`/{slug}`) | Central FAQ dataset |
| `GET /profiles` (+`/{slug}`) | People (doctors, executives, team) with typed roles |

## 6. Commerce flow

The active checkout path comes from `GET /config` → `checkout.path` (`prx` | `local`). Branch the whole flow on it — never assume one.

1. **Cart** — send `X-Cart-Token` (ULID) on every cart call; the backend mints one if absent (read it back from the response and persist client-side). `GET /cart`, `POST /cart/items` (`{type: product|package, id, plan_id?, quantity}`), `PATCH|DELETE /cart/items/{id}`.
2. **Upsells** — `GET /cart/suggestions` returns admin-curated Pairs With / Related light cards for the current cart (empty when the admin disabled upsells — just hide the placement). `config.checkout.upsells` carries the knobs. Products can be added directly (buy-once); link packages through to their page for plan selection.
3. **Lead** — `POST /leads` with customer identity + consents + UTM attribution; include `X-Cart-Token` to bind the cart. Returns a lead `uuid` **and `handoff_url`**.
4. **`prx` path (embed handoff — the default)** — after lead creation, redirect the browser to `lead.handoff_url`. That backend page hosts the provider embed with prefill + product selection already applied; clinical intake and payment happen there. Do **not** call `POST /checkout` on this path.
5. **`local` path** — `GET /checkout/gateway-config` for the tokenization SDK, then `POST /checkout` with `cart_ulid`, `lead_uuid`, and the tokenized `payment_method`. Order status afterwards: `GET /orders/{uuid}`.

## 7. Local development

- Point `API_BASE_URL` at the local backend vhost.
- If the backend uses an mkcert TLS cert, run Node with `NODE_OPTIONS=--use-system-ca` (mkcert installs its root CA into the OS trust store). This must be set in the shell/npm script — Node reads it at startup, so `.env.local` is too late. Do **not** disable TLS verification.
- A fresh backend has no CMS content: the config endpoint always answers, `/pages/home` 404s until the page is created in the admin. Build empty states accordingly.
- Neutral dev catalog: `php artisan db:seed --class=DevCatalogSeeder` (backend repo) seeds generic products/packages; `HomePageSeeder` seeds an **empty home-page scaffold** (8 standard section types, no content). Blueprint defaults are intentionally content-free, so nothing renders until content is authored in the admin (or loaded by a deployment-specific fill script kept in that deployment's frontend repo).

## 8. Hard rules for implementers

1. **No hardcoded branding.** Company name, logos, colors, copy, contact info, tracking IDs — all must come from the API. If you find yourself typing a brand string into a component, it belongs in the admin.
2. **Own your route patterns** for entity links; the backend only emits `{type, slug}`.
3. **Render unknown section types visibly in dev** (placeholder), silently skip in production — never crash on a new backend type. Either way, check `has_content` **first**: a section with none renders nothing, whether or not you have a component for its type. Empty scaffold sections may never leak onto a page.
4. **Never render an authored string as a text node.** Every operator-editable
   field is HTML — see 4a. Inline-kind fields go inside an element you choose;
   prose-kind fields get a container of their own.
5. **Respect `allow_indexing` and per-page `noindex`.**
6. **Keep API tokens server-side.**
