# Blog Module — Developer Guide

**Status:** Shipped 2026-06-22. Tag consolidation migration shipped 2026-06-27.

---

## Data model

```
blog_categories  <-[blog_category_post]->  blog_posts  <-[taggables (polymorphic)]->  tags
```

Tags were initially stored in a dedicated `blog_tags` table with a `blog_tag_post` pivot. Migration `2026_06_27_195400_consolidate_blog_tags_into_taggables` merged them into the shared polymorphic `tags` / `taggables` tables. There is no longer a `blog_tags` table.

### Tables

| Table | Purpose |
|---|---|
| `blog_posts` | Article records |
| `blog_categories` | Category records |
| `blog_category_post` | Many-to-many pivot: posts ↔ categories |
| `tags` | Shared tag pool (also used by catalog, FAQ, profiles) |
| `taggables` | Polymorphic pivot: tags ↔ any taggable model |

### Key columns

**`blog_posts`**

| Column | Type | Notes |
|---|---|---|
| `author_id` | `foreignId` | Nullable. `nullOnDelete`. Points to `users`. |
| `slug` | `string` unique | Route key. `GeneratesUniqueSlug` trait appends suffix on collision. |
| `status` | `string(16)` | Cast to `PostStatus` enum. Values: `draft`, `published`, `archived`. |
| `published_at` | `timestamp` nullable | Null = publish immediately. Future = scheduled. Indexed. |
| `featured` | `boolean` | Indexed. Filterable via API. |
| `gallery` | `json` nullable | Array of storage paths; resolved to full URLs in the resource. |
| `og_image_path` | `string(2048)` | Separate from hero; for social share override. |
| `position` | `unsignedInteger` | Managed by `SortableTrait`. |
| `created_by` / `updated_by` | `foreignId` | Audit columns. Nullable, `nullOnDelete`. |
| `deleted_at` | `timestamp` | Soft deletes. |

**`blog_categories`**

| Column | Type | Notes |
|---|---|---|
| `slug` | `string` unique | Route key. |
| `is_visible` | `boolean` | Gates API visibility. |
| `position` | `unsignedInteger` | `SortableTrait`. |
| `deleted_at` | `timestamp` | Soft deletes. |

---

## Models

### `App\Models\Blog\BlogPost`

- Implements `Sortable` via `SortableTrait` (`position` column, `sort_when_creating: true`).
- Uses `SoftDeletes`, `HasFactory`, `GeneratesUniqueSlug`, `HasTags`.
- `status` cast to `PostStatus` enum.
- `gallery` cast to `array`.
- `published_at` cast to `datetime`.

**Relationships:**
- `author()` — `BelongsTo(User, author_id)`
- `creator()` — `BelongsTo(User, created_by)`
- `updater()` — `BelongsTo(User, updated_by)`
- `categories()` — `BelongsToMany(BlogCategory)` via `blog_category_post`
- `tags()` — `MorphToMany(Tag)` via `HasTags` trait (polymorphic through `taggables`)

**Scopes / helpers:**
- `scopePublished(Builder)` — filters `status = published` AND `published_at IS NULL OR published_at <= now()`. Used by the API index query.
- `isPublished(): bool` — same logic, for single-record checks (used in `show` to gate 404).
- `getRouteKeyName()` — returns `slug`; enables route model binding by slug.

### `App\Models\Blog\BlogCategory`

- Implements `Sortable`, uses `SoftDeletes`, `HasFactory`, `GeneratesUniqueSlug`.
- `is_visible` cast to `boolean`.
- `posts()` — `BelongsToMany(BlogPost)` via `blog_category_post`.
- `getRouteKeyName()` — returns `slug`.

---

## Enums

**`App\Enums\PostStatus`** (`string` backed)

| Case | Value | Badge color |
|---|---|---|
| `Draft` | `draft` | gray |
| `Published` | `published` | success (green) |
| `Archived` | `archived` | warning (amber) |

---

## Actions

No dedicated Action classes exist for the Blog module. All writes go through standard Filament resource save handling (Eloquent directly). There are no API write endpoints — the blog is read-only from the API perspective.

---

## API endpoints

All blog endpoints are **public** (no authentication required) and are **read-only**. They are registered in `routes/api.php` under the `/api/v1/blog/` prefix.

Rate limiting inherits the global API rate limit configured on the `api` middleware group.

---

### `GET /api/v1/blog/posts`

Returns a paginated list of published posts, ordered by `published_at` descending. Loads `categories` and `tags` on every result.

**Query parameters:**

| Param | Type | Description |
|---|---|---|
| `per_page` | integer | Results per page. Default 15, max 50. |
| `category` | string | Filter by category slug. |
| `tag` | string | Filter by tag slug. |
| `featured` | boolean | When `true`, returns only featured posts. |
| `search` | string | `LIKE` filter on `title`. |

**Response shape (abbreviated):**

```json
{
  "data": [
    {
      "id": 1,
      "title": "Post title",
      "slug": "post-title",
      "excerpt": "Short summary…",
      "content": null,
      "hero_image_url": "https://example.com/storage/blog/posts/hero.jpg",
      "gallery": [],
      "status": "published",
      "featured": false,
      "published_at": "2026-06-01T12:00:00.000000Z",
      "read_time_minutes": 5,
      "author": null,
      "categories": [ { "id": 1, "name": "Wellness", "slug": "wellness", "description": null, "hero_image_url": null, "is_visible": true } ],
      "tags": [ { "id": 3, "name": "HRT", "slug": "hrt", "color": "#7c3aed" } ],
      "seo": null
    }
  ],
  "links": { ... },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, ... }
}
```

Note: `content` and `seo` are `null` on the list endpoint — they are only populated on the single-post `show` endpoint.

---

### `GET /api/v1/blog/posts/{slug}`

Returns a single published post. Returns 404 if the post does not exist, is not `published`, or has a future `published_at`.

Loads `author`, `categories`, and `tags`. Includes `content` and `seo` fields that are omitted from the list endpoint.

**Response shape (abbreviated):**

```json
{
  "data": {
    "id": 1,
    "title": "Post title",
    "slug": "post-title",
    "excerpt": "Short summary…",
    "content": "Full article body…",
    "hero_image_url": "https://example.com/storage/blog/posts/hero.jpg",
    "gallery": ["https://example.com/storage/blog/posts/gallery/img1.jpg"],
    "status": "published",
    "featured": false,
    "published_at": "2026-06-01T12:00:00.000000Z",
    "read_time_minutes": 5,
    "author": { "id": 1, "name": "Dr. Smith" },
    "categories": [ ... ],
    "tags": [ ... ],
    "seo": {
      "meta_title": "Custom SEO title",
      "meta_description": "Custom description",
      "og_image_url": null
    }
  }
}
```

---

### `GET /api/v1/blog/categories`

Returns all visible categories (`is_visible = true`), ordered by `position` then `name`. No pagination. Includes a `posts_count` derived column from the `withCount('posts')` call — but note: `BlogCategoryResource` does not currently expose `posts_count` in its output (only used in the admin table).

**Response shape:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Wellness",
      "slug": "wellness",
      "description": "Articles about healthy living.",
      "hero_image_url": null,
      "is_visible": true
    }
  ]
}
```

---

### `GET /api/v1/blog/categories/{slug}`

Returns a single visible category. Returns 404 if `is_visible` is false.

---

### `GET /api/v1/blog/tags`

Returns all tags from the shared `tags` table where `is_visible = true`, ordered by name. No pagination.

Tags are shared across blog, catalog, FAQ, and profiles. This endpoint returns the full visible tag pool, not just tags that happen to be attached to blog posts.

**Response shape:**

```json
{
  "data": [
    { "id": 3, "name": "HRT", "slug": "hrt", "color": "#7c3aed" }
  ]
}
```

---

## Filament admin resources

Both resources live under the **Blog** navigation group.

| Resource | Class | Nav sort |
|---|---|---|
| Posts | `App\Filament\Resources\Blog\Posts\PostResource` | 10 |
| Categories | `App\Filament\Resources\Blog\BlogCategories\BlogCategoryResource` | 20 |

Form and table logic is extracted into `Schemas/` and `Tables/` classes following the project convention. Both resources include `TrashedFilter` and bulk restore/force-delete actions for soft-delete management.

---

## Integration points

- **Shared tags:** `BlogPost` uses the `HasTags` concern (`MorphToMany` via `taggables`). Tags are managed in `App\Models\Catalog\Tag`. The `BlogTagController` queries `Tag` directly, not a blog-specific model.
- **Users (authors):** `author_id` is a nullable foreign key to `users`. The API exposes `{ id, name }` on the show endpoint only. Deleting a user nullifies the `author_id` (`nullOnDelete`).
- **Storage:** Images are stored on the `public` disk. `Storage::url()` is used in resources to resolve full URLs. Paths are stored relative (e.g. `blog/posts/hero.jpg`), not as full URLs.
- **SeoSettings:** The post and category forms include per-record SEO override fields. When blank, the frontend is expected to fall back to global SEO values from `GET /api/v1/settings/seo` (or equivalent). The backend does not merge them server-side.

---

## Design decisions and gotchas

- **Content field is list-excluded by design.** `BlogPostResource` uses `$this->when($request->routeIs('api.v1.blog.posts.show'), $this->content)` to omit the full body from list responses. This keeps list payloads small. The `seo` block uses the same conditional.
- **`scopePublished` enforces scheduled publishing.** A `published` status post with `published_at > now()` is excluded from the index query and blocked with a 404 on show. There is no separate "scheduled" status — scheduling is purely time-based.
- **Tags were consolidated in a later migration.** The original schema had `blog_tags` + `blog_tag_post`. These were merged into the shared `tags` / `taggables` tables by `2026_06_27_195400_consolidate_blog_tags_into_taggables`. Any code written before that migration date that references `blog_tags` directly is now wrong.
- **No API write endpoints.** Blog posts are authored entirely through the Filament admin. There is no public POST/PUT/DELETE surface for blog content.
- **No Actions layer.** Because there are no complex write flows, no `app/Actions/Blog/` directory exists. Filament handles saves directly via Eloquent. If write API endpoints are added in the future, introduce Action classes following the standard `DTO → Action (Transacts) → Service` pattern.
- **`BlogCategoryResource` does not expose `posts_count`.** The admin table counts posts via `withCount`, but the API resource shape does not include this field. Add it to `BlogCategoryResource::toArray()` if the frontend needs it.
- **Slug uniqueness.** `GeneratesUniqueSlug` appends a numeric suffix on collision (e.g. `my-post-2`). This only applies to programmatically generated slugs; the admin form lets operators set any slug and will surface a database unique constraint error if it collides.
