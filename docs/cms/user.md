# CMS — User Guide

**Audience:** Site administrators / content editors. **Permission required:** `super_admin` role (until granular Shield permissions are generated for the Pages resource).

## What it is

The CMS area at `/admin/pages` lets you build, edit, schedule, and publish marketing pages by composing them from **sections** — typed content blocks that mirror the design language of the public site (Hero, Pricing, FAQ, etc.). Each section's data is editable in a structured form; reorder sections by drag, toggle them on/off without deleting, set per-page SEO overrides, and schedule publish times.

## Quick map

| URL | Use |
|---|---|
| `/admin/pages` | List, search, filter pages by status |
| `/admin/pages/create` | Create a new page |
| `/admin/pages/{id}/edit` | Edit a page's metadata + sections |
| `/admin/media` | Upload and pick images (Curator media library) |
| `/{slug}` | The published page on the public site |

## Creating a page

1. **Pages → New page**.
2. Fill in **Title** (required). The slug auto-fills from the title; edit it before saving if you want.
3. **Status** — Draft (not visible) / Published (visible) / Archived (hidden, not deleted). Default: Draft.
4. **Publish at** — leave blank to publish immediately when status is Published. Set a future date to schedule.
5. **SEO overrides** — fill these to override the global SEO defaults from `/admin/settings/seo`. Leave blank to inherit.
   - **Meta title / description** — used as `<title>` and `<meta description>` instead of the global default.
   - **OG image path** — path to the social-share preview image. Upload via `/admin/media` and paste the resulting path here.
   - **Hide from search engines** — emits `noindex, nofollow` for this page even if global indexing is on. Use for pre-launch pages, internal-only landing pages, etc.
6. **Save**.

## Adding sections to a page

After saving, you'll land on the page's edit screen. Below the page form, the **Sections** panel manages the page's content blocks.

1. Click **New section**.
2. Pick a **type** from the dropdown. Each type has its own form:
   - **Hero** — headline + subhead + dual CTAs
   - **Stats marquee** — auto-scrolling value/label strip
   - **Results — typographic intro** — short bridging headline
   - **Pricing tiers** — 3-card pricing layout with featured highlight
   - **Physicians grid** — physician cards + credential pills
   - **Founders / story** — long-form physician portraits
   - **Benefits — for him / for her** — benefit cards with category pills
   - **How it works (steps)** — numbered process steps
   - **Testimonials — expert quotes** — endorsement cards
   - **Transformed — proof + quotes** — headline stats + testimonial grid
   - **FAQ** — collapsible Q&A list
   - **Final call-to-action** — closer banner with CTAs
   - **Text block** — generic prose section with rich text
   - **Image + text split** — 50/50 image and prose
   - **CTA banner** — single full-width CTA strip
   - **Features grid** — 2/3/4-column icon + title + body cards
   - **Video embed** — YouTube or Vimeo embed
3. (Optional) **Anchor ID** — sets the section's HTML `id` so you can link to `/{slug}#anchor` from elsewhere on the site. Examples: `pricing`, `faq`, `physicians`.
4. **Enabled** — toggle off to hide a section without deleting it. Useful for A/B tests or seasonal blocks.
5. Fill in the type-specific form. Required fields are marked.
6. **Create**.

## Reordering and editing

- **Reorder** — grab the drag handle on a row in the Sections table and drop it where you want. Save is automatic.
- **Edit** — click the row's edit pencil. The section type is locked after creation; if you need to switch types, delete and re-add.
- **Disable / enable** — quick toggle without leaving the list.
- **Duplicate** — coming in the next iteration.

## Image fields

This release uses path strings for images. Upload your image to `/admin/media` (Curator), copy the path, and paste it into the relevant field (e.g. Hero "image", Pricing tier image, Physician portrait). A future iteration will replace these with a one-click image picker.

Recommended image folder: `public/images/cms/{page-slug}/...` so each page's assets stay grouped.

## Publishing workflow

- **Draft** → page is not on the public site; only visible to admins.
- **Set status to Published, publish_at blank** → live immediately.
- **Set status to Published, publish_at = future date** → page stays draft-equivalent until that timestamp; flips live automatically when the date passes.
- **Status Archived** → 404 on the public site, but record is preserved.
- **Soft-delete** → use the row's delete action. Restore via the trashed filter on the list page.

## Public render

A published page is reachable at `https://your-site/{slug}`. The layout pulls global brand and SEO settings from `/admin/settings/*`; per-page overrides on this page take precedence.

To view a published page from the edit screen, use the **View public page** action in the top-right.

## Common operations

### Make a copy of an existing page
Not yet supported in v1. Workaround: create a new page, then re-add and re-fill its sections. Duplicate action coming soon.

### Reset a section to defaults
Delete the section, then re-add the same type. The form pre-fills with the section type's default content (matching the original ProtocolHRT home-page copy for the imported types).

### Take a section live but hidden
Add the section with **Enabled** off. When you flip it on later, it appears on the next page load — no other action needed.

### Stage a launch-day page
- Create the page, set status **Published**, set **publish_at** to launch day at the right hour.
- Sections + meta can all be authored ahead of time.
- The page stays effectively draft until the timestamp, then flips automatically.

## Limits

- **No live preview yet** — to see your edits, save the page and view it on the public site.
- **No audit trail / version history** — current state only. Coming with the Audit module.
- **No revisions** — saving overwrites the current draft.
- **Image picker is path-string only** — Curator media library is installed, but section forms use TextInput for image paths in this release.
- Section types whose Blade markup hasn't yet been adjusted to read DB data will render their hard-coded ProtocolHRT defaults regardless of the data you enter. As of this release, the following five sections fully read from the DB: **Hero, FAQ, Pricing tiers, How it works, Final call-to-action**, plus all five generic types (Text block, Image + text split, CTA banner, Features grid, Video embed). The remaining 8 imported sections will be wired up next.

## Troubleshooting

- **Section data saved but doesn't appear on the public site** — likely one of the not-yet-refactored section types. See note above. The list of fully-DB-driven sections is in `/docs/cms/dev.md`.
- **Page returns 404 on the public site** — check status is Published, publish_at is in the past or null, and slug matches the URL exactly. The catch-all route excludes `admin`, `horizon`, `livewire`, `media`, `reverb` slugs.
- **Save fails with validation error** — required fields (title, slug, headline on Hero, etc.) are missing or invalid. Fields highlight in red.
