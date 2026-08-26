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
2. Pick a **type** from the dropdown. Each type has its own form. **The dropdown is the
   authoritative list** — types get added, and any list written here goes out of date. They
   fall into families:

   - **Openers** — *Hero* (slideshow, CTAs, and optional highlight cards), *Highlight
     banner*, *Title banner* (a page setting rather than a section — see *Title banner* in the Page Builder guide).
   - **Prose and media** — *Text block*, *Image + text split*, *Image callout banner*,
     *Video embed*.
   - **Proof** — *Testimonials*, *Ambassadors / featured proof*, *Results — by the numbers*,
     *Stats marquee*, *Physicians spotlight*, *Founders / story*.
   - **Explainers** — *How it works (process)*, *Timeline (vertical steps)*, *Features grid*,
     *Benefits — for him* / *for her*, *Benefits diagram*.
   - **Catalog-driven** — *Product slider*, *Product grid*, *Product callout*, *Package
     slider*, *Package pricing comparison*, *Category grid*. These pull live data; see
     **Product sections** in the Page Builder guide for how the picks and rules work.
   - **Questions** — *FAQ* for a handful of one-off questions typed into the section itself,
     and *FAQ categories (from FAQ dataset)* to pull them from **Content → FAQ** instead, so
     one edit updates every page using it. Use the dataset version on the FAQ page.
   - **Pricing** — *Pricing tiers*.
   - **Closers** — *Final call-to-action*, *CTA banner*.

   If none of them fit, you can define your own type — see **Custom section types** in the
   Page Builder guide.
3. (Optional) **Anchor ID** — sets the section's HTML `id` so you can link to `/{slug}#anchor` from elsewhere on the site. Examples: `pricing`, `faq`, `physicians`.
4. **Enabled** — toggle off to hide a section without deleting it. Useful for A/B tests or seasonal blocks.
5. **Sections start empty.** A newly added (or freshly seeded) section renders nothing on the public site until you fill in its content and save — there is no placeholder copy to overwrite.
5. Fill in the type-specific form. Required fields are marked.
6. **Create**.

## Reordering and editing

- **Reorder** — grab the drag handle on a row in the Sections table and drop it where you want. Save is automatic.
- **Edit** — click the row's edit pencil. The section type is locked after creation; if you need to switch types, delete and re-add.
- **Disable / enable** — quick toggle without leaving the list.
- **Duplicate** — coming in the next iteration.

## Layout & spacing (every section)

Every section's edit form carries two collapsed panels at the bottom — **Layout & spacing**
and **Style**. Between them they cover the adjustments that used to need a developer. You
never type a pixel value or a colour code into either: every control is a dropdown from a fixed
scale (the one exception being the background image, which is a picker), so pages stay
consistent with each other and the whole scale can be retuned site-wide without anyone
re-editing content.

| Setting | What it does |
|---|---|
| **Content width** | Caps how wide the content runs and centres it inside the section. *Narrow* suits long-form reading; *Full* removes the cap. |
| **Media width** | Whether this section's image or video sits inside the content column or spans the section edge to edge. |
| **Horizontal inset** | Pulls text and buttons in from the left and right edges — this is the section's left/right padding. Background photos stay full width, so a hero image still bleeds edge-to-edge while its headline moves inward. *Flush* goes the other way and removes the page margin entirely, so content runs right to the screen edge. |
| **Content alignment** | Left / centre / right for headings, copy and buttons. |
| **Padding top** / **Padding bottom** | Breathing room above and below, set independently — "generous above, tight below" is sayable. |

### Three things about these controls that will not be obvious

**Leaving a control unset is not the same as choosing "None".** Unset means *use this
section type's design default*; **None** means *force zero, whatever the default is*. Today
they often look identical, because most types declare no default for most knobs — which
makes the difference invisible until a type gains one, and then a page you thought was
pinned quietly moves. If you mean zero, say **None**.

**There is no left or right padding, and that is deliberate — not an oversight.** The
horizontal edges belong to **Horizontal inset**, which acts on the content column. A
left/right padding control would act on the outer band instead, and would leave every
section that paints its own background inset from the screen edge by exactly the amount you
chose. One pair of horizontal controls, on the box that can move safely.

**Padding is listed under Layout & spacing but stored under a `style_` name.** You will only
ever see this if you or a developer look at the underlying data. The prefix stops the
setting colliding with a section's own content fields; it says nothing about which panel the
control belongs in.

### Responsive overrides (per screen size)

Under the controls is a **Responsive overrides** block with two tabs, **Tablet up** (768px)
and **Desktop up** (992px).

**The controls above the tabs are the value at every width, and the base value is the phone
value.** Each tab then changes it *from that breakpoint upwards*. So you build mobile-first
and add as the screen grows — which is the right way round here, because a phone usually
wants less of everything, and because most of this site's visitors are on one.

- A tab field left as *Same as narrower screens* genuinely inherits — it does not mean "no
  padding at this size".
- That inheritance is why **None** exists as a separate choice: it is the only way to
  *remove* something on desktop that you added on mobile.
- Four settings can differ by screen size: horizontal inset, content alignment, and the two
  paddings.
- **Content width and media width have no tabs, on purpose.** A content width is a maximum,
  so it does nothing on a screen narrower than the cap — a mobile override of it would be a
  control that cannot have an effect.

Resize the browser window to check this. A screenshot at one width cannot show you whether
an override is working.

## Style (every section)

The **Style** panel adds colour, a border, corners and a background image. Leave everything
unset to keep the section type's own design — an untouched section is not reset to plain.

| Setting | What it does |
|---|---|
| **Background colour** | Fills the section band edge to edge. Panels and cards inside keep their own styling. |
| **Text colour** | The colour copy inherits. Anything the section colours explicitly keeps its own. |
| **Accent colour** | Eyebrows, emphasised words, stat figures. Separate from text colour so the accent still stands out against it. |
| **Button colour** | Fills this section's buttons. |
| **Border colour** | Draws a border around the section band. |
| **Border width** | How thick the border is. The border is only drawn once a colour is chosen. |
| **Corner radius** | Rounds the band into a card. |
| **Background image** | Sits behind the section, covering the band. |

### Things worth knowing before you use it

**Colours are picked by name, from the site's palette** — not by typing a colour code. The
palette lives in **Settings → Theme → Colour palette**, and each entry is a name and a
colour. Because a section stores the *name*, retuning "sand" in the palette moves every
section using it, in one edit. It also means **a colour that sections are using cannot be
deleted or renamed** — the admin will stop you and tell you where it is used. Fix the
sections first, then remove the colour.

**A button works out its own label colour.** Pick any fill and the label is set to black or
white, whichever stays readable on it. You cannot produce an unreadable button, and you do
not get to choose the label colour — this is the single most surprising behaviour in the
panel and it is deliberate.

**Border width does nothing on its own.** With no border colour there is **no border at all** —
not a thin one. The reverse pairing is the one that falls back: choose a colour and leave the
width unset and you get a hairline. (The width control stays visible either way; it is the
border that is conditional.)

**A corner radius turns the band into a card.** It stops the section running edge to edge and
pulls it in from the screen edges so the corners are visible. That is the control working,
not a bug. To force square corners and *keep* the band full width, choose **None** — the same
"explicit zero" idea as the spacing controls.

Be aware of a wrinkle in the form here: when the radius is unset the dropdown displays
*"Square — band runs edge to edge"*, and the help text tells you to choose "Square". **There is
no Square option** — the choices are None, Small, Medium and Large. Unset and None both give
you square corners today, so nothing renders wrongly; it is the wording that misleads.

**Pair a background image with a background colour.** The colour shows while the image loads,
so text stays readable in the meantime.

## Cards inside a section

Some section types hold a repeating list of **content blocks** — cards you add, reorder and
remove, each with its own content and its own Layout & spacing and Style panels.

**Today this is the Hero only, and the block type is a testimonial card.** On the hero the
panel is called **Highlight cards** — add one or more and two or more turn into a small slider
overlaid on the slideshow. (A section type that gains blocks in future will show them under
the generic label *Content blocks* unless it renames the panel, as the hero does.)

- **Where the cards sit is a hero setting**, not a per-card one: **Highlight card position**
  offers a 3×3 grid of anchors — top / middle / bottom against left / centre / right.
- **The cards are not shown on phones at all** — below 576px the whole layer is hidden and
  there is no fallback, so they do not stack under the slide or appear anywhere else. There is
  not enough width to overlay a card without covering the headline. **Never put content here
  that a phone visitor needs to see**; most of this site's traffic is on one.
- The hero also keeps a collapsed **Highlight card (legacy single card)** panel. Those fields
  are used **only** when no content blocks are added; adding one supersedes them.
- A card's own panels read the same as a section's, with one exception: **Flush** is not
  offered on a card's horizontal inset, because a card is not next to the screen edge.

## Writing formatted text

Every text field is now a small editor with a toolbar — you format copy the way you
would in a document, and no longer need to type HTML by hand.

There are two toolbars, and which one you get depends on the field:

**Headings, eyebrows, titles, labels, quotes** — bold, italic, link.
These sit inside a heading or label whose size and style the page design owns, so
there are deliberately no heading buttons here. To break a headline across two
lines, press **Shift+Enter**:

> The Operating System *(Shift+Enter)* for Longevity

**Body, description, bio, FAQ answers** — the above plus headings (H2/H3), bulleted
and numbered lists, and quotes. Use these for anything long enough to need
structure, such as a legal page or a detailed answer.

A few things worth knowing:

- **Paste is cleaned up.** Pasting from Word or another web page keeps your bold,
  italics and links, and discards the formatting that would fight the site design.
  In a heading field, pasted headings and lists flatten to plain lines.
- **Blank lines are not spacing.** Pressing Enter repeatedly to push content down
  leaves empty paragraphs that are stripped on save. Use the layout controls under
  **Layout & spacing** instead.
- **Colour classes** still work through the link/HTML route:
  `<span class="tx-gold">coloured</span>` (classes come from Settings → Theme →
  text classes).
- **There is no character limit** on these fields any more. Keep headings short
  because the design expects it, not because the form will stop you.

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

### Reset a section
Delete the section, then re-add the same type. The form starts **empty** — sections have no default copy, and a section with no content renders nothing on the site. This is intentional: nothing appears on your pages that you didn't write.

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
- Section types whose Blade markup hasn't yet been adjusted to read DB data will render their hard-coded PrescribeRx Open Source Backend defaults regardless of the data you enter. As of this release, the following five sections fully read from the DB: **Hero, FAQ, Pricing tiers, How it works, Final call-to-action**, plus all five generic types (Text block, Image + text split, CTA banner, Features grid, Video embed). The remaining 8 imported sections will be wired up next.

## Troubleshooting

- **Section data saved but doesn't appear on the public site** — likely one of the not-yet-refactored section types. See note above. The list of fully-DB-driven sections is in `/docs/cms/dev.md`.
- **Page returns 404 on the public site** — check status is Published, publish_at is in the past or null, and slug matches the URL exactly. The catch-all route excludes `admin`, `horizon`, `livewire`, `media`, `reverb` slugs.
- **Save fails with validation error** — required fields (title, slug, headline on Hero, etc.) are missing or invalid. Fields highlight in red.
