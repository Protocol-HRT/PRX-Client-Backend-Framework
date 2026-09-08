# Referrals — developer guide

**Status: shipped 2026-09-03/04** — capture, admin CRUD, hierarchy, reps, funnel
metrics, commissions, the staff dashboard and the `/partner` portal. See "Not
built yet" at the foot for what remains.

## What this is

Attribution for referred traffic: who sent a visitor, whether they converted, and
enough evidence to settle a commission argument months later.

It is **entirely local and provider-agnostic.** Nothing here talks to
prescribe-rx, and that is a decision rather than an omission:

- Their intake embed **pins one sales organization per embed code** and exposes no
  per-session override, so a per-affiliate credit could not ride on an encounter
  even if we wanted it to.
- They have **no API for tracking links at all** — their link tracking fires only
  on their own public doors, via their own cookies.
- This backend ships to many deployments. Attribution keyed to one vendor's org
  tree could not ship in a generic product; a deployment that never uses
  prescribe-rx still has affiliates to pay.

## Data model

```
referral_sources ──┬── referral_links ──┬── referral_clicks ──── leads
 (who is credited)  │  (a code)          │  (the ledger)          (the conversion)
                    │                    │
      parent_id ────┘        restrict    └── nullOnDelete + string snapshots
   (ARBITRARY DEPTH
    self-reference)
```

**The hierarchy is arbitrary depth**, mirroring how prescribe-rx models sales
organizations — a national group over regions over individual reps, as deep as the
commercial structure goes. It was one level in the first cut and that was wrong: a
real sales org nests, and flattening it makes "my whole downline's numbers"
unanswerable.

- `descendantAndSelfIds()` walks DOWN, `ancestorAndSelfIds()` walks UP (the latter
  is what a commission roll-up will need).
- Backed by `staudenmeir/laravel-adjacency-list` — the same package prescribe-rx
  uses — so one recursive CTE answers at any depth and both systems describe a
  sales organisation the same way.
- **`enableCycleDetection()` is ON, and it is not optional.** A recursive CTE over
  a cycle does not return an empty set: on MySQL it errors, on SQLite it never
  terminates. It hung the test suite the moment the hand-rolled walk (which
  carried its own `$seen` guard) was swapped for the CTE. `preventCycles()` on
  `saving` is the first line; detection is the second, for a cycle arriving by raw
  query, import or restored backup.
- **`ancestorAndSelfIds()` uses `orderByDesc('depth')`, and the direction is a
  trap.** The package gives ancestors a NEGATIVE depth (self 0, parent −1,
  grandparent −2), so a plain `orderBy` returns the ROOT first — silently, since
  both orderings return the same ids. A commission roll-up walking leaf→root would
  credit the tree upside down. prx has two adjacent methods with opposite
  orderings and the same "closest first" comment; a test pins ours.
- **`descendantAndSelfIds()` is memoised per process**, because one dashboard
  render asks for it from the table and each widget independently. The memo is
  dropped on every `saved`/`deleted`, so a re-parent is never served stale.
- **`User::visibleReferralSourceIds()` is the single scoping point the panel
  actually uses** (via `PartnerResource::getEloquentQuery()`); the model's
  `scopeVisibleTo()` is the same rule expressed as a query scope, kept for
  callers that want to compose it. Both resolve a missing viewer to **no rows,
  never all rows**.

| Table | Role | Deleted? |
|---|---|---|
| `referral_sources` | affiliate / sales_group / partner / internal | soft |
| `referral_links` | one public code, optional expiry | soft |
| `referral_clicks` | **one row per arrival — append-only** | never |
| `leads.referral_*` | the conversion, written once | with the lead |

### The three rules that make it auditable

1. **The ledger is append-only.** `referral_clicks` has no `deleted_at` and no
   `prunable()`. Contrast `Commerce\Cart`, which *is* pruned at 90 days — that is
   exactly why attribution does not live on the cart, and why `Cart::prunable()`
   needs no new guard for referrals.
2. **String snapshots outlive the joins.** `referral_clicks.code` /
   `source_slug`, and `leads.referral_code`, duplicate what the foreign keys say.
   When a source is finally deleted the FKs go null and these still name who was
   credited. Read the code when the question is *who was credited*; read the joins
   when the question is *and are they still active*.
3. **Nothing cascades into attribution.** `referral_links.referral_source_id`
   **restricts** on delete, so a source with links cannot be hard-deleted at all.
   prescribe-rx's own equivalent cascades here while their
   `leads.tracking_link_id` is `nullOnDelete`, so deleting one source silently
   nulls the attribution on every lead it produced. A loud failure beats that.

**There are no `clicks` / `conversions` counter columns, deliberately.** A
commission cannot be argued from an incremented integer. Counts are derived:

```sql
-- total and unique clicks for a code
SELECT COUNT(*) AS total, COUNT(DISTINCT visitor_id) AS unique_visitors
FROM referral_clicks WHERE code = ?;

-- conversions
SELECT COUNT(*) FROM leads WHERE referral_code = ?;
```

## The capture path

```
visitor lands on  https://atlasprotocol.com/?ref=LT-4F2A
   │
   ├─ atlas-protocol-web/middleware.js   (edge — the ONLY thing that sees this request)
   │     sets  atlas_ref=lt-4f2a  and  atlas_vid=<uuid>   (30 days, host-only, not httpOnly)
   │     POST /api/v1/referrals/clicks   with the visitor's real IP forwarded
   │
   ├─ RecordReferralClickAction → one referral_clicks row (idempotent per visitor+code)
   │
   └─ later: quiz or checkout submits a lead carrying referral_code + referral_visitor_id
         └─ AttributeLeadAction → binds source/link/click onto the lead, write-once
```

**Why the click is written server-side.** Middleware runs on our server, so the
real client address arrives via `X-Forwarded-For` (rightmost entry only — a
visitor may prepend anything) and is trusted because `TRUSTED_PROXIES` already
names that host. **That is the part that cannot be faked, and it is the only
part.**

**What the ledger is NOT.** `POST /referrals/clicks` is anonymous and publicly
reachable — going through the storefront's server buys a trustworthy IP, not
exclusivity — so anyone can post to it directly with a fresh random `visitor_id` per request and mint
"unique" clicks — the throttle and the uuid check bound the rate and the shape,
not the intent. **Clicks are therefore evidence, not a payable quantity.** This is
safe only while commissions are paid on CONVERSIONS, which require a real
checkout. If per-click payment is ever agreed, this endpoint needs authentication
first. When item 43 step 2 lands it is the strongest candidate for `auth:sanctum`
plus an ability, with the throttle keyed on the forwarded address rather than the
shared client id.

**Why the middleware awaits the POST.** Fire-and-forget work in middleware can be
killed when the response is sent, and a lost click is an unpayable commission.
Repeat landings on a code the visitor already carries short-circuit on the cookie
before any fetch, so the cost is paid once in the normal case; an arrival under a
*different* code does pay it again, because that arrival still has to be recorded
even though first-touch means it will not win the credit. The fetch is bounded by
a 2s timeout, so a dead backend degrades to "cookie set, click missing" rather
than a hung landing page.

**Why the cookies are not httpOnly.** A referral code is printed on flyers, not a
secret, and the quiz and checkout forms read it at submit time. The thing that
must not be forgeable is the ledger, and that is written server-side.

## Behaviours worth knowing

| Situation | What happens |
|---|---|
| Unknown / retired / expired code | Click **is still recorded**, with null FKs. "My flyer sent 400 people and I was paid for none" is answerable. |
| Source deactivated (`is_active = false`) | Its links stop resolving immediately — `ReferralLink::usable()` checks the source, so links need not be switched off one by one. |
| Visitor refreshes the landing page | One click. `firstOrCreate` against a UNIQUE `(code, visitor_id)` index, so it holds under a race rather than by check-then-insert. |
| Code that lengthens when lowercased | Refused, and **the lead still saves**. `İ` × 64 lowercases to 128 chars; normalising must happen BEFORE bounding or the value overflows the column and 422s every later submit. `ReferralLink::normalizeCode()` is the single place this is done. |
| Visitor arrives under a second code | **First touch wins**, both in middleware and in `AttributeLeadAction`. The second arrival is still recorded, so a last-touch model stays derivable if terms ever change. |
| Cookie lost before converting (new device, cleared storage) | `AttributeLeadAction` falls back to the code alone: source and link bind, `referral_click_id` stays null. |
| Code cased differently | Matched case-insensitively. `ReferralLink` lowercases on save and `resolve()` lowercases on read — **the two must stay in step**. |
| Forged `visitor_id` | Refused. A non-uuid would otherwise mint one "unique visitor" per request. |

## API

`POST /api/v1/referrals/clicks` — anonymous, `throttle:30,1`, write-only.

```json
{ "code": "LT-4F2A", "visitor_id": "<uuid>", "landing_url": "...", "referrer": "...", "utm_source": "..." }
→ 200 { "data": { "recorded": true } }
```

Always 200, even for a code matching nothing — this fires on a visitor's first
page view and a 404 would only teach the caller to retry. The body reports
`recorded` and **nothing else** — not the source, not the slug, not even whether
the code is live. An earlier version returned `resolved`, which no caller read and
which handed a prober one bit per request toward enumerating the affiliate
roster. There is **no GET** here for the same reason; reading referral
performance belongs to the partner panel behind a session.

`POST /api/v1/leads` additionally accepts `referral_code` and
`referral_visitor_id`. Attribution runs **after** the lead is created, in its own
action, because a lost commission is recoverable from `referral_clicks` and a lost
lead is not.

## Gotchas

- **`referral_links.code` is stored lowercase**, and every path goes through
  `ReferralLink::normalizeCode()` — the model's `saving` hook, `resolve()`, both
  actions and the storefront middleware. Lowercase FIRST, then bound: Unicode
  case-folding can lengthen a string, and bounding first is how a crafted `?ref=`
  link became a denial of checkout. Write through the model, never a raw query.
- **The frontend proxy allowlist is not involved.** Middleware calls the backend
  server-side, so `lib/backendProxy.js` needs no entry. Adding one would expose a
  ledger-writing endpoint to the browser.
- **The referral columns are deliberately NOT in `Lead::$fillable`.** That is what
  makes write-once structural: `AttributeLeadAction`'s `forceFill` is the only
  door, so a future Filament form, dedup grouping or webhook handler cannot
  mass-assign over a credit. Adding them back re-opens it silently — there is a
  test pinning this.
- **Attribution is write-once.** Re-running the action on an already-attributed
  lead is a no-op, by design. Reassigning a credit is a commission dispute.

## Metrics — `App\Services\Referral\ReferralMetrics`

The funnel: **clicks → unique visitors → leads → conversions → revenue**, plus the
two rates.

**Everything rolls up through the tree by default, and that was the bug that made
the hierarchy look broken.** Clicks land on the LEAF that owns the code, so a
parent counting only its own rows reported zero however much its downline
produced. `funnel($source, rollup: false)` gives direct-only, which is meaningful
only next to the rolled-up figure.

**A conversion is a CAPTURED payment**, not a lead and not an authorisation.
Authorised-but-never-captured is money nobody received; paying commission on it
would be paying out of pocket.

**Rates are computed against UNIQUE visitors**, not raw clicks — one person
reloading a landing page five times has not become five prospects.

`breakdown($source)` returns one row per DIRECT child, each rolled up through its
own subtree, plus the source's own direct activity labelled `(direct)`. Direct
children rather than every descendant, because "a group sees aggregate broken down
by its sub-orgs" means one line per sub-org; each drills in for the rest. **This is
deliberately NOT what prx does** — its breakdown groups by whichever node owns the
order, so a grandchild appears beside its parent and the parent's row excludes its
own downline.

One service for every level, because a national group, a regional group and an
individual partner all read the same dashboard shaped to their scope. Two
implementations would drift, and the one an affiliate sees is the one nobody
checks.

## People (reps)

A rep is a **`User` with `referral_source_id` set**, exactly as prescribe-rx models
one — not a separate contact table, because the point of a rep is that they sign
in, and `users` already carries auth, roles and invitations.

- **`users.referral_source_id` is `restrictOnDelete`, and that is a SECURITY
  decision.** `nullOnDelete` looked kinder, but this column is what
  `User::canAccessPanel()` reads to decide someone is a partner rather than staff:
  nulling it on an org deletion would silently PROMOTE every affiliate in that org
  to a staff-eligible account. Hard-deleting an org with people now fails loudly.
  Soft deletes are unaffected and remain the normal path.
- **The panel gate is a TYPE boundary, not a permission one.** Anyone carrying a
  `referral_source_id` is refused the staff admin outright — it holds even if
  someone later assigns them a staff role by mistake. This is the distinction prx
  draws between its portals.
- **`User::visibleReferralSourceIds()` is the single chokepoint.** Own org plus
  its entire downline; `null` for staff meaning "not scoped by this at all"; and
  **`[]` — never all rows — for a partner whose org was deleted**, which is the
  classic permissive-default leak.
- Partner roles (`affiliate`, `sales_group`) carry **zero permissions** by design;
  the gate does the work, and empty roles are a second wall rather than a
  coincidence.
- Reps are created from the org's own **People** panel with a random password
  nobody is told — they arrive via password reset, so no credential is ever
  transmitted or sits in an admin's clipboard.

## Admin

`ReferralSourceResource` (Leads → Referral sources) with a `LinksRelationManager`
for the codes. Codes live on the source's page rather than in a top-level list
because a code with no source to credit is meaningless.

- **Minting** is `MintReferralCodeAction`: `{prefix}-{6 chars}`. The alphabet
  excludes `0 o 1 l i` — codes get dictated and typed off print, and ambiguity
  costs more than the ~1 bit of entropy. `codeIsTaken()` is **public and uses
  `withTrashed()`**, because a soft-deleted link still holds its code under the
  unique index. It is public so that property can be asserted directly: testing it
  through `execute()` is theatre, since the 31^6 tail space means a random
  collision never occurs in a test run. (That test existed, passed under mutation,
  and was rewritten.)
- **Minting happens in the CreateAction, not a model hook** — generating a code is
  a decision, not a property of saving, and an operator who typed their own keeps
  it.
- **QR codes** are `ReferralLink::qrCodeSvg()`, via `chillerlan/php-qrcode`
  (already vendored — no new dependency). SVG at ECC level H, because these get
  printed and creased. Rendered on demand from `url()` and **never stored**: a QR
  is a view of the code, not a second record that could drift out of step.
- **`ReferralLink::url()`** builds from `BrandSettings::$site_url`, since this
  backend drives a decoupled storefront and must not invent an origin. It appends
  `ref` with `&` when `destination_path` already carries a query string. Both
  `url()` and `qrCodeSvg()` return null when no site URL is configured, so the
  admin degrades quietly rather than throwing.
- **There is no delete action on codes.** A printed code cannot be un-printed and
  its clicks are commission evidence; deactivating stops it earning and keeps the
  trail.

## Commissions

**Telescoping override bands**, the standard shape for a sales hierarchy:

```
partner 10%, their group 15%, national 20%, on a $100 sale
cumulative   10  /  15  /  20
bands        10  /   5  /   5     →  $20 total, i.e. the TOP rate
```

Each tier earns the difference between its rate and what its downline already
claimed. **Get this wrong and a four-level tree pays out 60% of revenue.** A
parent whose rate is lower than its child's earns a zero band, never a negative
one — the cumulative figure is clamped non-decreasing going up.

`referral_commissions` holds one row per (conversion, node). Three properties,
each with a test:

- **The rate is SNAPSHOTTED onto the row.** Renegotiating an affiliate's
  percentage next quarter cannot rewrite what they were owed last quarter. A
  system that recalculates from live rates cannot answer "why was I paid this",
  which is the only question ever asked of it.
- **Paid is final.** A recompute updates pending rows in place — it is keyed on
  (lead, source) so it cannot double-pay — but it refuses to touch a `paid` row,
  because that money has left.
- **The row still names its payee after the source is deleted** (`source_slug`,
  `source_name`, `referral_code` snapshots), and a lead carrying commissions
  cannot be hard-deleted at all.

**NOTHING TRIGGERS THIS ON THE ATLAS DEPLOYMENT TODAY, AND THAT IS THE FIRST
THING TO FIX.** `leads.payment_status` is written by exactly one class,
`ProcessCheckoutPaymentAction`, which has **zero callers** anywhere in `app/`,
`routes/` or `tests/`; and this install runs `payment_collector: provider` with
`checkout_path: prx`, so prescribe-rx collects and this app never captures at all.
The commission engine is therefore correct and unreachable: conversions, revenue
and commission owed are structurally zero until a prescribe-rx order/payment
webhook feeds `payment_status`. Our webhook handler does not consume one — and see
PENDING TODO 19, it does not even match their event names. **Do not read a zero on
the dashboard as "no sales"; read it as "no signal".**

Written by `LeadObserver` on the `payment_status` TRANSITION to captured — not on
the value, so a later edit to an already-captured lead does not re-enter it. The
observer swallows its own failure: a commission that failed to compute is
recoverable (re-run the action), whereas throwing would roll back the payment
capture that just succeeded.

## Dashboards

**One widget set serves both panels**, shaped by the viewer — a national group, a
regional group and a solo affiliate read the same dashboard with their own
numbers. Two implementations would drift, and the one an affiliate sees is the one
nobody checks.

`App\Filament\Concerns\ResolvesReferralScope` is the single place that decides
whose numbers are shown. **`null` means unscoped (staff); `[]` means an empty
scope and MUST show nothing.** Collapsing the two would turn a deleted
organisation into a full data leak — `ReferralMetrics::funnelForIds()` preserves
the distinction and is the reason it takes `?array` rather than `array`.

| Widget | Shows |
|---|---|
| `ReferralFunnelWidget` | clicks / unique / leads / conversions / revenue / commission owed |
| `ReferralBreakdownWidget` | one row per organisation, each rolled up through its own downline |
| `RevenueChartWidget` | revenue and sales, 30 days, dual axis |

**There was no dashboard page at all before this** — the admin panel was
`->pages([])` with no dashboard route, so the three widgets that already existed
were discovered by Filament and rendered nowhere. `App\Filament\Pages\Dashboard`
wires them up alongside the referral ones.

## The `/partner` panel

A second Filament panel, not policy scoping inside the admin. The measurement
behind that: the staff panel has **39 resources, 11 pages, 20 relation managers,
25 of which expose PII, credentials or margin — and not one scopes rows.** The
only `getEloquentQuery()` override in the entire admin adds a `withCount`.

- **Audience is a TYPE boundary, not a permission.** `User::canAccessPanel()`
  makes the two panels mutually exclusive on `referral_source_id`, so it holds
  even if a partner is handed a staff role by mistake.
- **Every partner resource extends `PartnerResource`**, which owns
  `getEloquentQuery()` scoping and per-record `canView()`. **`PartnerPanelTest`
  enumerates the panel and asserts it** — prescribe-rx's own tenancy audit found
  20 cross-tenant leaks whose root cause was "only 4 of 277 models carry the
  scope trait". The failure mode is never a wrong scope, it is a screen nobody
  thought about, so forgetting is a failing test rather than a review question.
- **Authorization is panel membership + row scoping, NOT the model policy**, and
  the override is deliberate. Shield keys permissions on the model, so a partner
  resource over `ReferralSource` consults the admin's `ReferralSourcePolicy` and
  403s, since partner roles hold zero permissions. Granting
  `ViewAny:ReferralSource` would be the wrong fix — that same key governs the
  STAFF resource and its unscoped organisation list.
- **Shield is not registered on this panel** for the same model-keying reason;
  generating there would re-stub the admin's policies.
- **Partners never delete.** Codes, organisations and people all carry commission
  history; deactivation is the verb.
- A partner may add sub-organisations and people, but **may not set commission
  rates** — that would let a group change what the business pays out through the
  telescoping bands. Parent choices are restricted to their own downline, so a
  new organisation cannot be attached anywhere else in the tree.

**One behaviour worth knowing:** the adjacency package builds its CTE on
`newModelQuery()`, so **soft-deleted descendants stay in `descendantAndSelfIds()`**
and keep contributing to a parent's rolled-up figures, while the partner panel's
own query hides those rows. Not a leak — the ids never reach a list — but a
deactivated sub-organisation's history still counts toward its parent's totals,
which is what you want for commission and might surprise on a click count.

## Not built yet

- **THE CONVERSION SIGNAL — the blocker for everything else.** For a
  provider-collected deployment, `payment_status` has to be driven by the
  prescribe-rx webhook. Until then every money figure on every dashboard is zero
  by construction.
- **Commission PAYMENT.** The ledger records what is owed and carries
  `approved` / `paid` / `void` with `paid_at` and `payment_reference`, but nothing
  moves a row through those states yet — no approval screen, no payout run, no
  statement. That is the next piece.
- **A commissions resource on both panels** — staff to approve and mark paid,
  partners to see their own statement. The model, scopes (`outstanding()`,
  `payable()`) and snapshots are all in place.
- **Date-range filtering on the dashboards.** `ReferralMetrics` already takes
  `$from`/`$to`; the widgets pass neither.
- **Partner invitations.** A rep is created with a random password and is expected
  to arrive via password reset; nothing sends that email yet.

## Tests

- `ReferralAttributionTest` — 26, capture and attribution.
- `ReferralAdminTest` — 22, hierarchy, minting, URL/QR, ancestor ordering, memo
  invalidation, and Livewire renders of every screen (a 302-to-login proves
  routing, not that the derived-count closures survive a real request).
- `ReferralMetricsTest` — 9, built on the operator's own tree: a group over two
  sub-groups over five partners.
- `ReferralRepScopingTest` — 10, the no-bleed guarantees.
- `ReferralCommissionTest` — 10, band arithmetic and settlement immutability.
- `DashboardTest` — 5, the staff dashboard renders with data AND with none.
- `PartnerPanelTest` — 9, including the structural scoping test.

**A fixture note that cost time:** `UserFactory` does not set `is_active`, relying
on the column default, so a freshly built instance carries NULL in memory while
the row is true — and `! $this->is_active` in the panel gate then denies a user who
is really active. Filament always loads users from the database, so it is a test
artifact rather than a defect; set it explicitly in fixtures. The durability ones are
the point: a click still names its source after the source is force-deleted, a
source holding links cannot be force-deleted, attribution is never reassigned, and
counts are derivable from the ledger alone. Seven mutations were run against them (removing the write-once guard, removing
click idempotency, dropping the `source_slug` snapshot, dropping the visitor-uuid
check, dropping the length bound in `normalizeCode`, re-adding the referral
columns to `Lead::$fillable`, and dispatching `LeadCreated` before attribution);
each killed exactly the intended test.
