# Settings — User Guide

**Audience:** Site administrators / operations staff. **Permission required:** `super_admin` role (or any role with the relevant settings permission once Shield permissions are generated for these pages).

## What it is

The Settings area at `/admin/settings/*` controls the brand, look, contact info, and SEO/analytics defaults that flow through the public site and admin panel. **Everything here is database-driven** — changes take effect on the next page load with no code deploy.

## Quick map

| Section | URL | What it controls |
|---|---|---|
| **Brand** | `/admin/settings/brand` | Brand name, tagline, logo, favicon, hero image |
| **Theme** | `/admin/settings/theme` | Primary / accent / background / text colors, display & body fonts |
| **Contact** | `/admin/settings/contact` | Support & sales emails, phone, mailing address, business hours, social links |
| **SEO & Analytics** | `/admin/settings/seo` | Default meta title & description, OG image, Google Analytics, Tag Manager, Facebook Pixel, search-engine indexing toggle |

## How to edit

1. Sign in at `/admin/login` with a `super_admin` account.
2. In the left sidebar, open the **Settings** group and pick a section.
3. Edit the fields. Required fields are marked.
4. Click **Save** (or `Cmd/Ctrl + S`). A green toast confirms the save.

Changes are live immediately — no server restart, no cache clear.

## Field-by-field notes

### Brand

| Field | Notes |
|---|---|
| Brand name | Used in the page `<title>`, the `og:site_name` meta tag, mail-from name, and any spot the layout asks for the company name. |
| Tagline | One-line positioning. Used in OG previews and as a layout subhead. |
| Logo path | A path under `public/` (e.g. `/images/logo.svg`). Upload the file via SFTP or the media library when that ships. |
| Favicon path | Usually the same as the logo for SVG assets. |
| Hero image path | Optional fallback for OG previews and the home hero block. |

### Theme

Colors must be hex codes (`#0d0d0d`, `#c19a4b`). The five color tokens map onto CSS variables `--bg-primary`, `--text-body`, etc., used by the `data-theme="light"` / `data-theme="dark"` swap throughout the public site.

Font names should be the family name as used by `@font-face`. The actual font files live under `public/fonts/` and aren't editable from this UI yet.

### Contact

Email and URL fields are validated. Country code is the ISO 3166-1 alpha-2 code (US, CA, GB, …). Social URLs that you leave blank are skipped in the rendered footer/nav.

### SEO & Analytics

| Field | Notes |
|---|---|
| Default page title | Used as `<title>` when an individual page doesn't override it. |
| Default meta description | Used as `<meta name="description">` and OG description. |
| Default OG image | Used as `og:image` when a page doesn't override. |
| GA4 measurement ID | `G-XXXXXXX`. When set, the layout emits the GA4 snippet. |
| GTM container ID | `GTM-XXXXXXX`. When set, the layout emits the GTM snippet. |
| Facebook Pixel ID | When set, the layout emits the Pixel snippet. |
| Allow indexing | Off in staging / pre-launch. When off, every page emits `<meta name="robots" content="noindex, nofollow">`. |

## Common operations

### Take the site out of search-engine indexing

`/admin/settings/seo` → toggle **Allow indexing** off → Save. Confirm by viewing the public source and looking for `<meta name="robots" content="noindex, nofollow">`.

### Replace the brand mark

Upload your new logo to `public/images/your-logo.svg` (SFTP). Then in `/admin/settings/brand` set **Logo path** to `/images/your-logo.svg` and Save. Hard-refresh the public site.

### Stop using a Google service

Clear the relevant ID field (GA / GTM / Pixel) and Save. The layout stops emitting the snippet immediately.

## Limits

- These settings are **single-tenant per deploy** — there is no per-user or per-region override.
- Settings are NOT versioned or audit-logged in this iteration. (Coming with the Audit module.)
- Image uploads from the UI are NOT yet supported — paste a path string. (Coming with the Media Library module.)

## Troubleshooting

- **Saved but the public site didn't change** — full-page-refresh. If still stale, check whether view caching is on (`php artisan view:cache` was run); run `php artisan view:clear` to invalidate.
- **Validation error toasts** — the form schema validates first, then the DTO validates again. The error message names the field. If a field appears valid but the toast persists, check that no leading/trailing whitespace snuck in.
- **403 on `/admin`** — your account doesn't have the `super_admin` role. Ask another `super_admin` to assign it via Shield's user-roles UI.
