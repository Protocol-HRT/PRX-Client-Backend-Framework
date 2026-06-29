# Blog Module — Operator Guide

## Overview

The Blog module lets you publish articles, organize them into categories, and tag them for filtering. All published content is served to the frontend via the public API — no authentication required from readers.

The admin panel has two sections under the **Blog** navigation group: **Posts** and **Categories**. Tags are shared with the Catalog module and are managed under **Catalog > Tags**.

---

## Posts

A **Post** is a single blog article. Navigate to **Blog > Posts** to create, edit, or manage posts.

### Key fields

| Field | Description |
|---|---|
| Title | The article headline. Shown in listings and browser tabs. |
| Slug | URL path for the post (e.g. `my-article`). Auto-generated from the title on create. Change with caution — altering a slug after publishing breaks any existing links or bookmarks. |
| Excerpt | Short summary (150–300 characters recommended). Shown in listing cards and used as the default OG description. |
| Content | Full article body. Supports Markdown or plain text depending on how your frontend renders it. |
| Status | Controls visibility. See status table below. |
| Publish at | Optional scheduled publish time. Leave blank to go live immediately when status is set to Published. If set to a future date/time, the post will not appear publicly until that moment even if status is Published. |
| Position | Numeric sort order. Lower numbers appear first within sorted listings. |

### Post statuses

| Status | Behavior |
|---|---|
| Draft | Hidden from the public API. Work in progress. |
| Published | Visible via the API. If a Publish at date is set in the future, the post remains hidden until that time passes. |
| Archived | Hidden from the public API. Preserved for reference. |

### Featured section

| Field | Description |
|---|---|
| Featured post | Toggle on to mark this post as featured. The frontend can use this flag to highlight posts in a hero slot or featured carousel. |
| Read time (minutes) | Displayed to readers alongside the post. Enter manually or compute based on content length. |

### Imagery

| Field | Description |
|---|---|
| Hero image | Primary image shown at the top of the post and in social share previews. Max 5 MB. Stored at `blog/posts/`. |
| Gallery images | Additional images for an in-post gallery. Up to 12 images, 5 MB each. Reorderable. Stored at `blog/posts/gallery/`. |

### Categories & Tags

Assign the post to one or more categories and apply optional tags. Both support search and multi-select.

- **Categories** control which archive pages the post appears on (e.g. `/blog/category/wellness`).
- **Tags** are optional labels for filtering and related-post suggestions. Tags come from the shared tag pool managed under Catalog > Tags.

### SEO overrides

These fields override the global SEO settings for this specific post page.

| Field | Description |
|---|---|
| Meta title | Page title used by search engines. Defaults to the post title if blank. |
| Meta description | Search engine snippet. 150–160 characters recommended. |
| OG image | Open Graph image for social shares. 1200×630 px recommended. Overrides the hero image if set. |

Leave all SEO fields blank to fall back to the global SEO settings configured at **Settings > SEO**.

### Soft deletes

Deleting a post moves it to trash. It can be restored from the trashed filter in the post list. Use **Force delete** to permanently remove a post.

---

## Categories

Categories organize posts into named sections. Navigate to **Blog > Categories** to manage them.

### Key fields

| Field | Description |
|---|---|
| Name | Category label shown in navigation and on archive pages. |
| Slug | URL path for the category archive (e.g. `wellness`). Auto-generated from name on create. |
| Description | Optional intro text displayed under the category heading on archive pages. |
| Visible | When toggled off, this category is hidden from the public API. Posts assigned to it are still accessible directly but the category will not appear in category listings. |
| Position | Numeric sort order. Lower numbers appear first in navigation. |
| Hero image | Banner image shown at the top of this category's archive page. Max 5 MB. |

### SEO overrides

Same pattern as posts: meta title and meta description override the global SEO settings for this category page. Leave blank to inherit global defaults.

### Soft deletes

Same as posts — delete moves to trash, restore brings it back, force delete is permanent.

---

## Tags

Tags are managed under **Catalog > Tags** because they are shared across blog posts, products, packages, plans, FAQ items, and profiles.

Each tag has a name, slug, optional color, and a Visible toggle. Only visible tags are returned by the blog tags API endpoint.

To use a tag on a blog post, it must first exist in Catalog > Tags and be marked visible.

---

## Workflow: publishing a post

1. Go to **Blog > Posts > New post**.
2. Enter a title — the slug is auto-filled.
3. Write the excerpt and content.
4. Upload a hero image.
5. Assign at least one category.
6. Set **Status** to **Published**. Optionally set a **Publish at** date/time to schedule it.
7. Save. The post is immediately (or at the scheduled time) visible via the API.

## Workflow: creating a category before writing posts

Create categories before writing posts so you can assign them during authoring.

1. Go to **Blog > Categories > New category**.
2. Enter a name; the slug auto-fills.
3. Toggle **Visible** on.
4. Set a position if order matters in your frontend navigation.
5. Save.

---

## Gotchas

- **Changing a slug after publishing breaks URLs.** The frontend uses slugs as URL paths. If you must rename a slug, coordinate with whoever manages the React frontend to add a redirect.
- **A Published post with a future Publish at date is still hidden.** The post only becomes visible once the scheduled time passes. The API enforces this server-side.
- **Tags must be created in Catalog > Tags.** You cannot create a new tag from inside the post form — only select from existing visible tags.
- **Category visibility is independent of post visibility.** Hiding a category removes it from the categories API list but does not hide the posts assigned to it. Those posts remain accessible by direct slug lookup.
