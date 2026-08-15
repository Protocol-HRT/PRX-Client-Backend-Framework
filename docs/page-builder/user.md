# Page Builder — Admin Guide

This guide covers building and managing site content: pages and their sections,
custom section types, reusable global blocks, menus, the site layout (header,
footer, sidebars), images, and version history.

**Everything you edit here goes live on the website immediately after saving.**
The site refreshes within seconds — there is no separate "publish" step for
content edits (pages themselves still have Draft/Published status).

## Pages & sections

**Content → Pages.** Each page has settings (title, URL slug, status, SEO) and a
list of **sections** — the building blocks the website renders in order.

- **Add a section**: open a page → Sections → New. Choose either **New section**
  (pick a type and fill in its fields) or **Reuse a global block** (see below).
- **Reorder** by dragging rows. **Disable** a section to hide it from the site
  without deleting it.
- **Anchor ID** gives the section an in-page link target (e.g. `pricing` lets
  menus link to `#pricing`).
- A section's **type cannot change after creation** — delete and re-add instead.

### Title banner

In the page settings, enable **Title banner** to show a banner above the page
content: background image, title (defaults to the page title), subtitle, intro
text, and optional breadcrumbs.

### Product sections

Four section types pull live product data from the Catalog:

- **Product slider / Product grid** — pick products by hand, or choose a rule:
  *Featured*, *Newest*, or *All in a category* (with a limit). Rule-based
  sections update themselves as the catalog changes.
- **Product callout** — spotlight one product or package with custom headline,
  copy, and image (falls back to the catalog content when left blank).
- **Package pricing comparison** — pick 2–3 packages; the site renders
  comparison columns with live plan pricing.

Only **published** catalog items ever appear on the site; unpublished picks are
skipped automatically.

## Custom section types

**Content → Custom Section Types.** When the built-in types don't fit, define
your own: give it a name and add fields from the palette (text, multi-line text,
rich text, image, SVG, link, toggle, dropdown, repeating items, product/package
pickers).

- **Repeating items** hold a set of sub-fields admins can "add another" of —
  e.g. an "Ingredient Spotlight" with repeating {icon, title, blurb} items.
- Field **keys** and the type's **slug** cannot be renamed after creation (the
  website's renderer depends on them). Plan them with your developer.
- A type **in use cannot be deleted** — disable it instead (its sections stop
  rendering until re-enabled).
- Note: the frontend renders custom types with a generic layout keyed on field
  kinds. For a bespoke design, ask your developer to build a dedicated component
  for that type's slug.

## Global blocks (reusable sections)

**Content → Global Blocks.** A global block is one section defined once and
reused in many places (pages and layout regions). Editing the block updates
every page that references it, instantly.

- Reference one from a page: Sections → New → **Reuse a global block**.
- **Detach copy** (scissors icon on the section row) converts a reference into
  an independent copy — it keeps the current content but stops following the
  block.
- Blocks **in use cannot be deleted**; disable them (they disappear from the
  site everywhere) or detach the references first.

## Menus

**Content → Menus.** Create named menus (e.g. `main`, `footer-company`) and add
items. Items can link to a page, product, package, catalog category, blog post,
blog category, a custom URL, or an in-page anchor (`#pricing`). Optional per
item: open in new tab, icon, highlight badge (e.g. "New"), nesting under a
parent item (up to 3 levels).

Good to know:
- Internal links **follow renames automatically** — renaming a product updates
  every menu that links to it.
- If a link target is unpublished or deleted, that menu item (and its children)
  simply disappears from the site until fixed — the site never shows dead links.

## Site layout (header, footer, sidebars)

**Content → Site Layout.** The global page chrome is composed of six regions:
top bar, header, pre-footer, footer, left sidebar, right sidebar. Each region
holds an ordered list of items: a **section** (owned by the region), a **global
block**, or a **menu**. Typical setup: a menu in the header, menus + a CTA
global block in the footer, an announcement section in the top bar.

## Images

Image fields use the **media library** (Content → Media): pick an existing image
or upload right from the picker. Set **alt text** in the media library — the
website uses it for accessibility and SEO.

## Version history (revisions)

Every page keeps snapshots of its previous states (page settings + all
sections). Open a page → **Revisions**: *Inspect* shows the captured content;
*Restore* returns the page to that state. Restoring is itself undoable — the
current state is snapshotted first. The last 30 snapshots per page are kept.

## SVG fields — a note

SVG markup pasted into custom sections is automatically stripped of anything
executable (scripts, event handlers) before it reaches the website. If a pasted
icon renders empty, it likely wasn't valid SVG — re-export it from your design
tool.
