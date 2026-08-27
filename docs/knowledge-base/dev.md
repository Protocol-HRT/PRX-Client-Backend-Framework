# Knowledge Base — Developer Guide

Compound monographs: long-form reference pages summarised from the operator's clinical
literature corpus, served to the frontend as public content and linked to the catalog by
ingredient. A clinician byline is optional and usually absent — see §2.

This is phase 1 of the goals-to-protocol module. It ships alone and depends on nothing: the
health-goal taxonomy, the goal↔compound index, protocols and the plan report are later
phases and are named here only where a column exists to receive them.

---

## 1. Data model

### `compounds`

One row per compound, independent of whether this install sells it.

| Group | Columns |
|---|---|
| Identity | `name`, `slug` (unique), `tagline`, `brand_names` (json), `synonyms` (json) |
| Classification | `compound_class`, `is_peptide`, `regulatory_status`, `route_of_administration` |
| Monograph | `description`, `overview`, `mechanism_of_action`, `pharmacology`, `clinical_evidence`, `dosing_guidelines`, `safety_profile`, `patient_summary`, `clinical_references` (json) |
| Ranking | `evidence_tier`, `evidence_score` |
| Commerce | `ingredient_id` → `ingredients` |
| Review | `reviewed_by_profile_id` → `profiles`, `reviewed_at`, `review_notes` |
| Publication | `is_published`, `published_at` |
| SEO | `meta_title`, `meta_description`, `hero_image_path`, `og_image_path` |
| Provenance | `source_system`, `source_ref`, `content_model`, `content_generated_at`, `source_document_count`, `source_dosing_count` |
| Standard | `position`, `created_by`, `updated_by`, timestamps, soft deletes |

Unique on `(source_system, source_ref)`; index on `(is_published, is_peptide)`.

Model: `App\Models\Kb\Compound` — Spatie `HasSlug` (`preventOverwrite`, matching Product and
Package), `SortableTrait` on `position`, `SoftDeletes`, `HasItemSections`, and
`getRouteKeyName() === 'slug'`.

### Why not extend `ingredients`

`ingredients` is the catalog's lookup table — thin, provider-syncable, and it exists to drive
a facet on the shop listing. A monograph is editorial: long-form, reviewed by a named
clinician, and most rows will never be a product (of 102 imported compounds, 7 match an
ingredient). Merging them would put a sync target and a reviewed document in one row.
`compounds.ingredient_id` links the two instead — and that link is what the public monograph
uses to list the products containing the compound, which is the one thing a generic health
wiki cannot publish.

### Why `compound_class` is not a taxonomy

It is carried over from the source for provenance and search only. In the seed data **94 of
106 rows say only "Peptide"** — including amoxicillin, adapalene and vitamin B12 — and the
remainder mix mechanism, effect and chemistry. Health goals are the taxonomy, and they arrive
with phase 3.

---

## 2. The publication gate

**A monograph is public only when it is published AND has a regulatory status.** One rule, one
place:

```php
public function scopePublished(Builder $query): Builder
{
    return $query->where('is_published', true)
        ->whereNotNull('regulatory_status');
}
```

`CompoundController::show()` re-asserts it via `isPublishable()`, because route-model binding
resolves by slug regardless of publication. The admin's publish toggle, the navigation badge
and the *Needs a status* tab all count the same condition, so none of them can drift into
showing an operator a state the API will refuse.

**Why the status is in the query and not in a comment.** When it is null the public page
renders no not-approved notice and the structured data carries no `legalStatus` — so a
research compound goes live reading exactly like an approved medicine. A missing status is
not a blank field, it is a missing warning.

`ImportCompoundsCommand` never sets `is_published`, and the imported status is a *seeded
suggestion* an operator confirms per compound. If you find yourself adding a `--publish` flag,
that is the decision you are actually making.

### Why a clinician reviewer is NOT required

It was, and it was removed deliberately. Read this before reinstating it.

This content is **summarised from the operator's own clinical literature corpus** — millions
of clinical excerpts and white papers — by a retrieval pipeline with Bedrock doing the
condensation. It is not authored by one of their providers, and it is not a reproduction of an
FDA label either.

Requiring a provider's name before ~100 pages can publish makes "attach one doctor to all of
them" the path of least resistance. That produces a byline asserting a clinical review that
did not happen, on medical content — which is worse than no byline at all. The gate would have
manufactured attributions rather than reviews.

What actually stops unread drafts going live is `is_published = false` on import: an operator
has to act per page. The byline was never doing that work.

The field stays, and renders when set, for the cases where a clinician genuinely reads a page.
`Compound::factory()->live()` deliberately does **not** attach one, so the fixtures teach the
real rule; `->reviewed()` adds the byline for the tests that render it.

**When it is set, the reviewer is a `Profile`, not a `User`** — it has to be displayable with
credentials ("Reviewed by Jane Roe, PharmD"), and `profiles` already carries name / title /
credentials / bio. Users are admin accounts and have no credentials field. The form offers
profiles of type `doctor` and `subject_matter_expert`.

### Provenance is the substitute, and it is a stronger claim

`source_document_count` and `source_dosing_count` are the pipeline's retrieval counts, and the
public page leads its provenance block with them: *"Summarised from 43 clinical sources in our
research library."* Measured against the reference lists, they are genuine retrieval counts
rather than citation counts — they differ in 84 of the 89 rows that carry one.

That is content nobody else can publish, which is the defensible position for this kind of
page. A clinician byline is a claim any site can make; the size of a proprietary evidence base
is not.

`source_preclusion_count` is deliberately **not** imported: it reads exactly 100 on all 106
source rows, so it is a retrieval cap, not a count, and publishing a constant as evidence
would be a false precision. A zero document count is reported as *absent*, not as zero —
"0 sources" reads as a failure, and "unrecorded" is what actually happened.

`Compound::booted()` keeps `published_at` in step with `is_published`, so an unpublish cannot
leave a stale date for the sitemap to emit.

---

## 3. `RegulatoryStatus`

`App\Enums\Kb\RegulatoryStatus`, string-backed, surfaced on every public page and mapped into
schema.org `legalStatus`.

| Value | Label | Means |
|---|---|---|
| `fda_approved` | FDA approved | An FDA-approved drug product exists and this is it. |
| `investigational` | Investigational | In FDA-registered clinical trials. Not approved for sale. |
| `research_only` | Research use only | Supplied for laboratory research. No approved human use. |
| `compounded` | Compounded preparation | Dispensed by a 503A/503B pharmacy, not as an approved product. |
| `supplement` | Dietary supplement | Regulated as a supplement, not a drug. |
| `unapproved` | Marketed without FDA approval | Sold in the US with no approval behind it. |

**One value, not a set.** Several overlap in the real world — semaglutide is approved *and*
widely compounded; retatrutide is investigational *and* sold as a research chemical — and a
set would let an operator publish a monograph claiming both "approved" and "not for human
use". The rule for picking: describe the compound **as this pharmacy supplies it**.

`null` is "nobody has decided yet", which is why `published()` refuses it and the admin's
*Needs a regulatory status* filter and *Needs a status* tab both surface it.

`isApprovedForHumanUse()` drives two things on the public page: a warning notice above the
prose, and the `warning` property in the structured data. Both are emitted only when it is
false — attaching a scare to an approved drug would devalue it where it matters.

---

## 4. Import

```bash
php artisan kb:import-compounds <dump.sql> --curation=<curation.json> [--dry-run] [--force]
```

Two files, two responsibilities:

- **The dump** is content — a mysqldump of prescribe-rx's `protocol_compounds`.
- **The curation sidecar** is the deployment's editorial judgement about that content:
  display names, URL slugs, `is_peptide`, `regulatory_status`, and which rows are the same
  compound written twice. Keeping them apart is what lets this command ship in the generic
  backend while the decisions live with the install that made them. `--curation` is required;
  without it every row would import with no display name, no slug and no peptide flag.

For this deployment, both live in the frontend repo alongside the other Atlas-specific fill
scripts:

```bash
php artisan kb:import-compounds \
    /var/www/html/atlas-protocol-web/docs/prx-peptide-kb.sql \
    --curation=/var/www/html/atlas-protocol-web/scripts/atlas-kb-curation.json \
    --dry-run
```

### `SqlDumpReader`

Reads INSERT rows straight out of a `.sql` file — no scratch database, no second connection.
It understands only mysqldump's grammar: `INSERT INTO \`t\` (cols) VALUES (…),(…);` with NULL,
bare numerics, and single-quoted literals using MySQL backslash escapes. Everything else in
the file is skipped, including the CREATE TABLE.

`\%` and `\_` keep their backslash, because MySQL does — they are special only to `LIKE`, and
resolving them would silently alter the text. A file that ends mid-escape or mid-literal
throws the command's own error rather than a PHP stack trace, and a duplicate `generic_name`
warns instead of one row quietly replacing another.

Multiple rows per statement and multiple statements per file are both normal — the Atlas dump
is **106 rows across 103 statements**, which is why counting `(`-tuples with a regex reports
209 (it also counts each statement's column list). Verified byte-exact against MySQL's own
load of the same file: 2,756 fields compared, zero differences.

### `MonographMarkdown`

The source prose is **markdown**, not plain text: all 106 rows use `##` headings, 105 use
`**bold**` and bulleted lists, and 86 carry pipe tables of dosing titration. Storing it raw
would show an operator literal `##` in the rich editor and print them as text on the page.

Converted once, on the way in, with CommonMark plus the GFM table extension, then through
`HtmlCopy::prose()` so an imported value and a hand-edited one are the same shape.

**Headings drop one level.** The public page gives each monograph field its own `<h2>`; the
source's `##` headings sit inside one of those fields, so emitting them as `<h2>` would make
them siblings of their own container. h1/h2 → h3, anything deeper → h4, **in a single pass** —
two chained regexes demote an original `##` twice.

It introduces no facts and moves no content between fields. Restructuring prose into typed
cards is phase 2 and belongs behind the review gate.

### Re-import

Rows are keyed on `(source_system, source_ref)`, so a second run updates rather than
duplicates, and `slug` is excluded from updates — it is the public URL, and moving it should
be a deliberate act with a redirect behind it.

**A monograph with a reviewer attached is skipped** unless `--force`. The seed is a moving
target (it was generated by a model against prescribe-rx's sources and may be regenerated);
losing a review pass to a re-import is expensive in a way that re-running the command is not.

Every optional column is read defensively — a regenerated dump that drops `evidence_tier`
should import without that field, not abort halfway through a hundred rows. `generic_name`
and `id` are the two it refuses to proceed without: they are the curation key and the
re-import key.

---

## 5. API

Public, unauthenticated, `throttle:api`.

| Endpoint | Notes |
|---|---|
| `GET /api/v1/kb/compounds` | Paginated (`{data, links, meta}`). Filters: `search`, `peptides_only`, `regulatory_status`, `sort`, `per_page` (1–100, default 24) |
| `GET /api/v1/kb/compounds/{slug}` | Full monograph. 404 unless published **and** carrying a regulatory status |

**`peptides_only` defaults to TRUE.** The seed formulary is roughly two thirds antibiotics,
topicals and vitamins; the default answer to "what is in this knowledge base" should be the
peptide wiki. Pass `peptides_only=0` for the whole library — the frontend's sitemap does,
because every published monograph is a real URL.

An **unrecognised** `regulatory_status` matches nothing rather than being ignored. Silently
returning the unfiltered list reads to the caller as "every compound has that status".

`CompoundResource` gates the eight prose sections, `clinical_references`, `seo` and
`provenance` to the `show` route. At roughly 28,000 characters per compound, an index that
shipped them would send megabytes to render a list of names.

`regulatory` is an object — `{value, label, description, is_approved_for_human_use}` — rather
than a bare string, because the frontend has to render a label, colour a badge and decide
whether to show a notice. Deriving all three from an enum value would put this app's
regulatory vocabulary in the frontend, where a new case renders as unstyled text.

### Cache invalidation

`Compound` is observed by `CmsCacheObserver`, and `FrontendRevalidator::tagsFor()` emits
`cms`, `kb`, and `kb:{slug}` on every save. The frontend tags its KB fetches identically. A
new KB endpoint without a tag goes stale for the full ISR window.

---

## 6. Admin

`App\Filament\Resources\Kb\Compounds\CompoundResource` — navigation group **Content**, labelled
*Knowledge base*, with a warning badge counting monographs that still have no regulatory
status — the same condition `published()` enforces, so the badge cannot point at work the
publish toggle would accept.

List tabs lead with **Needs a status** deliberately: after an import this list is a hundred
summarised monographs nobody has looked at, and the first thing an operator needs is what is
blocking publication, not an alphabetical library.

Form tabs: Identity / Classification / Monograph / Review / SEO.

Two form details are load-bearing:

- **The publish toggle is disabled until a regulatory status is set**, and its helper text
  names the blocker. The API enforces the same condition; saying it twice means an operator
  does not discover the rule by publishing a page that then fails to appear, and naming the
  blocker means a greyed-out toggle is not a mystery.
- **The monograph fields' toolbar includes `table` and excludes `h2`.** 82 imported
  monographs carry dosing-titration tables, and Filament's editor drops markup it has no
  extension registered for — a toolbar without `table` would silently strip a titration
  schedule the first time anyone opened and saved the row. `h2` is excluded because the public
  page supplies each field's own `<h2>`, so the top level available inside a field is `h3`.

Permissions are Shield-generated (`*:Compound`); `BaseRolesSeeder` grants them to
`content_editor` alongside the other content models.

---

## 7. Tests

- `tests/Feature/Api/V1/Kb/CompoundEndpointTest.php` — the gate from both routes, **the
  reviewer being optional**, provenance on both routes, a zero source count reported as
  absent,
  `peptides_only` defaulting, the unknown-status filter, index/detail field gating, the
  products block, `published_at` derivation.
- `tests/Feature/Kb/ImportCompoundsCommandTest.php` — dedupe, alias merging without prose
  merging, markdown conversion including tables, single-pass heading demotion, idempotency,
  the reviewed-row skip and `--force`, `--dry-run`, ingredient linking, and refusal without
  curation.

Each guard has been watched to fail on the bug it targets — the scope without its
regulatory-status clause, **the scope with a reviewer clause reinstated**, the controller
without its `abort_if`, the status filter ignoring an unknown value, `peptides_only`
defaulting off, `published_at` not being cleared, the double-demotion, a zero source count
published as zero, and the `kb` revalidation tags.

---

## 8. What phase 1 deliberately leaves undone

- **`evidence_tier` / `evidence_score` are NULL on all 102 rows.** The source columns exist
  and were never populated. They rank compounds against a health goal, which is phase 3; the
  columns are here now because adding one to a reviewed table later is more disruptive than
  carrying two nulls.
- **`tagline` and `route_of_administration` are empty.** Route is present in the prose of 99%
  of rows and is extractable without new facts — that is phase 2's restructuring pass.
- **`catalog_item_sections`.** `Compound` already uses `HasItemSections`, so pointing the
  typed-section system at monographs is a one-line morph when phase 2 needs cards.
- **Timelines, plain-language exclusions and cost framing.** Measured across all 106 source
  rows, these appear in 31% / 25% / 26% respectively. **The information is not in the source**,
  so a model cannot supply it without inventing — and inventing a timeline for a research
  compound is exactly the claim not to make. That is authoring work and it does not shrink by
  adding a model.
