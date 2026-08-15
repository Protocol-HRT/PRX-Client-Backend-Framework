# prx-backend — Implementation Plan

> **HISTORICAL DOCUMENT.** This was the original build plan (June 2026) and is kept for context on why decisions were made. For the current-state architecture, see [`dev.md`](dev.md) — where the two disagree, `dev.md` wins. Known drift: the patient portal shipped under `/api/v1/patient/*` (not `/api/v1/portal/`), and the affiliate portal has not been built.

**Last updated:** 2026-06-27  
**Status:** Superseded — historical reference  
**Repo:** https://github.com/acappel01/prx-backend  

This document is the single source of truth for what we are building, why each piece exists, the order to build it in, and how all surfaces interact. Update it as decisions change.

---

## What this system is

A **brand-agnostic Laravel API backend + Filament admin panel** that each telemedicine client deploys as their own instance. Each deployment:

- Drives a **decoupled React/Next.js patient-facing website** via `/api/v1/`
- Drives a **decoupled React/Next.js patient portal** via `/api/v1/portal/`
- Hosts a **Filament admin panel** for operators, clinical staff, and affiliate managers
- Hosts a **Filament affiliate portal** for ambassadors, influencers, and referral partners
- Integrates with **prescribe-rx** as the canonical clinical backend (encounters, prescriptions, labs, orders, fulfillment)
- Processes payments locally via **NMI** or **Authorize.Net**, or routes them through prescribe-rx's own payment flow

No client branding, credentials, or data ever appears in this codebase. Every client instance is a fresh database configured entirely through the admin UI.

---

## System surfaces and their users

```
┌────────────────────────────────────────────────────────────────────────┐
│  PUBLIC WEBSITE  (Next.js — separate repo)                             │
│  Unauthenticated + pre-intake visitors                                 │
│                                                                        │
│  • Marketing pages served from CMS API (/api/v1/pages)                │
│  • Blog (/api/v1/posts)                                                │
│  • Product/package catalog (/api/v1/products, /api/v1/packages)        │
│  • Cart + lead capture (/api/v1/cart, /api/v1/leads)                  │
│  • PRX embed-form intake handoff (/checkout/handoff/{uuid})            │
│  • Affiliate entry points (?ref=CODE, /a/{code})                      │
│  • Theme driven by ThemeSettings API (/api/v1/theme)                  │
└────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────┐
│  PATIENT PORTAL  (Next.js — separate repo or subdomain)                │
│  Authenticated patients (patient-scoped PRX token)                    │
│                                                                        │
│  • Order history + shipment tracking                                   │
│  • Prescription viewer (drug, dose, refills, instructions)             │
│  • Lab results (biomarkers, PDFs)                                      │
│  • Subscription management (pause, cancel, swap protocol)              │
│  • Provider messaging                                                  │
│  • Profile + communication preferences                                 │
│  • HIPAA data export request                                           │
│  All via /api/v1/portal/* (Sanctum patient tokens)                    │
└────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────┐
│  FILAMENT ADMIN  /admin                                                │
│  Operators, clinical staff, billing                                    │
│                                                                        │
│  Non-PHI sections (all admin roles):                                   │
│  • Brand/theme/SEO/contact settings                                    │
│  • CMS pages + content sections                                        │
│  • Blog management                                                     │
│  • Product/package/plan catalog                                        │
│  • Leads + CRM overview                                                │
│  • Merchant account configuration                                      │
│  • Affiliate program management                                        │
│  • API token management                                                │
│  • Webhook configuration                                               │
│                                                                        │
│  PHI-gated sections (care_coordinator, clinical_reviewer, provider):   │
│  • Patient list + demographics (PRX-sourced)                           │
│  • Encounter viewer + clinical notes (PRX-sourced)                     │
│  • Prescription viewer (PRX-sourced)                                   │
│  • Lab results viewer (PRX-sourced)                                    │
│  • Provider queue / wizard review (Livewire)                           │
│  • PHI access audit log viewer                                         │
└────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────┐
│  FILAMENT AFFILIATE PORTAL  /affiliates                                │
│  Ambassadors, influencers, referral partners                           │
│                                                                        │
│  • Dashboard: clicks, conversions, earnings this period                │
│  • Links + coupon codes: generate, manage, view per-link stats         │
│  • Commission history: per conversion, per period                      │
│  • Payout history + request payout                                     │
│  • Profile + payment details (encrypted)                               │
└────────────────────────────────────────────────────────────────────────┘
```

---

## Role matrix

| Role | Non-PHI admin | PHI read | PHI clinical detail | Affiliate mgmt | Billing |
|---|---|---|---|---|---|
| `super_admin` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `admin` | ✓ | — | — | ✓ | ✓ |
| `care_coordinator` | ✓ | ✓ | — | — | — |
| `clinical_reviewer` | read-only | ✓ | ✓ | — | — |
| `billing` | ✓ | name/amount only | — | — | ✓ |
| `provider` *(future)* | read-only | ✓ | ✓ (own patients) | — | — |
| `affiliate` | — | — | — | own portal only | — |

**PHI custom permissions** (beyond Shield defaults):
```
view_phi                  Master gate — any PHI section at all
view_patient_records      Patient list + demographics
view_encounter_details    Encounter answers + clinical notes
view_prescriptions        Prescription drug/dose/instructions
view_lab_results          Biomarker values + PDFs
process_refunds           Billing actions on orders
manage_provider_queue     Provider-facing encounter review flow
```

---

## Architecture layers

```
React/Next.js (public site + patient portal)
        │  HTTP  →  /api/v1/*  (Sanctum token)
        ▼
   API Controller  →  validates → FormRequest
        │
        ▼
   DTO (spatie/laravel-data)
        │
        ▼
   Action (single-purpose, DB::transaction via Transacts trait)
        │  ↘  Service (business logic, external API calls)
        │  ↙  returns DTO / Resource
        ▼
   API Resource  →  JSON response

Filament / Livewire (admin)
        │  user action
        ▼
   DTO  →  Action  →  Service  →  Response DTO  →  Toast
```

**Rules that never change:**
- API controllers do not touch the DB directly — validate → DTO → Action
- Livewire components do not touch the DB directly — same rule
- Actions own DB transactions (`$this->tx(fn() => ...)`)
- Services own business logic and external API calls
- DTOs flow both directions — no raw arrays crossing layer boundaries
- PHI is fetched from PRX at view time; not persisted locally unless structurally required

---

## Data ownership map

| Data | Owned by | Stored where | Local reference |
|---|---|---|---|
| Patient demographics | PRX | PRX database | `prescribe_rx_patient_id` on `patient_profiles` |
| Encounters + intake answers | PRX | PRX database | `prescribe_rx_encounter_id` on `leads` |
| Prescriptions | PRX | PRX database | queried via API at view time |
| Lab orders + results | PRX | PRX database | queried via API at view time |
| Subscriptions | PRX | PRX database | queried via API at view time |
| Orders (clinical) | PRX | PRX database | `prescribe_rx_order_id` on local `orders` |
| Orders (local shell) | prx-backend | local DB | full local record for payment reconciliation |
| Payments / transactions | prx-backend | local DB (encrypted) | `merchant_accounts`, `transactions` |
| Products/packages (catalog) | prx-backend | local DB | `prescribe_rx_product_id` maps to PRX inventory |
| Leads | prx-backend | local DB | links to PRX patient/encounter on conversion |
| CMS content | prx-backend | local DB | — |
| Affiliate + commissions | prx-backend | local DB | — |
| Brand/theme settings | prx-backend | local DB (settings table) | — |

---

## PRX integration overview

prescribe-rx is the canonical clinical backend. We integrate via a Sanctum-format bearer token (sales-org scope) stored encrypted in `IntegrationSettings`.

**Currently implemented:**
- `GET /telehealth/encounter-types` — list available encounter types
- `GET /telehealth/encounter-types/{id}/schema` — dynamic intake question schema
- `POST /telehealth/intake/unified` — submit patient + encounter + intake answers atomically

**Planned integrations (as PRX builds the endpoints):**
- `POST /patients/{id}/issue-token` — issue patient-scoped token for portal auth *(gap #1 — critical)*
- `GET /lab-orders/{id}/results` — lab biomarker values *(gap #2)*
- `GET /patients/{id}/prescriptions` — prescription list/detail *(gap #11)*
- `GET /patients/{id}/encounters` — encounter history *(gap #16)*
- `GET /patients?email={email}` — patient deduplication *(gap #15)*
- `GET /telehealth/intake/status/{idempotency_key}` — submission recovery *(gap #13)*
- `PATCH /encounters/{id}/payment-reference` — record local payment capture *(gap #14)*
- Webhook signing secret on registration *(gap #12 — security)*
- `GET /webhooks/event-types` — enumerate valid event slugs *(gap #6)*
- See `docs/architecture/PRX_API_GAPS.md` for the full gap list with rationale

---

## Affiliate + attribution architecture

### Tracking flow

```
Visitor arrives at yourbrand.com/?ref=AMBCODE
        │
        ▼
TrackingMiddleware reads ?ref= or cookie
        │  logs ReferralClick (ip_hash, utm_*, landing_page)
        │  sets first-party cookie: ref_code (30-day)
        ▼
Visitor browses, selects products, fills intake
        │
        ▼
Lead captured locally  →  ref_code stamped on Lead
        │
        ▼
Intake submitted to PRX  →  Order created
        │
        ▼
ConversionEvent fired  →  ReferralConversion created
        │  links: affiliate, code, convertible (Order/Subscription)
        │  calculates commission from Affiliate.commission_structure
        ▼
Commission aggregated monthly  →  Payout batch
```

### Attribution models supported
- **Last-click** (default): most recent `ref_code` before conversion gets credit
- **First-click**: originating code gets credit (configure per affiliate)

### Commission structure types
- Flat per conversion (e.g. $25 per completed intake)
- Percentage of order total (e.g. 15% first order)
- Recurring percentage (e.g. 10% of each subscription renewal)
- Tiered: volume thresholds unlock higher rates
- Per-product overrides: different rates per protocol

### Coupon codes vs links
Influencers often prefer discount codes over tracking links. A `ReferralCode` can be of type `link` (URL-based, cookie-set) or `coupon` (entered at checkout, applied as discount + attribution event). Both flow through the same commission engine.

---

## Implementation phases

### Phase 0 — Foundation ✅ COMPLETE
- [x] Laravel 13 + Filament 4 + Livewire 3 + Sanctum skeleton
- [x] Auth, users, roles (Spatie Permissions + Filament Shield)
- [x] Settings (Brand, Theme, Contact, SEO, Integrations)
- [x] CMS (Pages + 18 typed section blueprints + API)
- [x] Blog (posts, categories, tags)
- [x] Catalog (Products, Packages, Plans with provider ref IDs)
- [x] Cart (session-based model)
- [x] Leads (capture model)
- [x] Orders + Encounters (models + Filament admin)
- [x] PrescribeRx Client Phase 1 (listEncounterTypes, getEncounterTypeSchema, submitUnifiedIntake)
- [x] Merchant Accounts (NMI + Authorize.Net, PaymentGatewayInterface, PaymentGatewayManager)
- [x] OpenAPI docs via dedoc/scramble at /api/docs
- [x] GitHub repo: https://github.com/acappel01/prx-backend

---

### Phase 1 — Commerce backbone
**Goal:** Complete the checkout flow end-to-end. A visitor can select products, capture a lead, hand off to the PRX embed, and the resulting order is recorded locally.

**Dependencies:** None — entirely self-contained.

**Deliverables:**

#### 1.1 Orders API
- `GET /api/v1/orders/{uuid}` — patient-facing order status (status, items, shipment tracking)
- `GET /api/v1/orders` — authenticated patient's order list
- Filament: add tracking info display to OrderResource

#### 1.2 Cart → Checkout API
- `POST /api/v1/cart/items` — add item
- `DELETE /api/v1/cart/items/{id}` — remove item
- `GET /api/v1/cart` — current cart with pricing
- `POST /api/v1/checkout/initiate` — validate cart, capture lead, build PRX embed prefill payload
- Response includes: `embed_url`, `lead_uuid`, `prefill_payload` for the frontend to launch the embed form

#### 1.3 PRX Webhook receiver
- `POST /api/webhooks/prescribe-rx` — receive + verify signed webhooks from PRX
- Handle events: `order.created`, `encounter.updated`, `prescription.signed` (full list pending PRX gap #6 + gap #12)
- Reconcile local order shells with PRX order status
- Route: public (no Sanctum), verified by HMAC signature

#### 1.4 Checkout handoff page
- `GET /checkout/handoff/{uuid}` — minimal Blade page that injects the PRX embed form with prefill data
- No branding (clients add their own via ThemeSettings loaded client-side)
- Handles return/cancel callbacks from the embed SDK

---

### Phase 2 — Patient auth + portal API
**Goal:** Patients can authenticate and access their clinical history via the portal API. Unblocks the React patient portal build.

**Hard dependency:** PRX gap #1 (patient token issuance endpoint) must ship on the PRX side first.

**Deliverables:**

#### 2.1 PHI foundation
- `phi_access_logs` table: `user_id`, `resource_type`, `resource_id`, `action`, `ip`, `user_agent`, `accessed_at`
- `LogsPhiAccess` trait for Filament pages + Livewire components
- PHI custom permissions seeded + assigned to roles (see role matrix above)

#### 2.2 Patient profiles (local shadow records)
- `patient_profiles` table: `uuid`, `user_id` (nullable), `prescribe_rx_patient_id`, `lead_id`, encrypted demographics
- `patient_provider_links` table: maps patient → PRX patient_id per provider/install
- Not a full copy of PRX patient data — just enough to link local records to PRX IDs

#### 2.3 Patient auth endpoints
- `POST /api/v1/portal/auth/login` — validate credentials locally, exchange for PRX patient token via gap #1, return short-lived portal token
- `POST /api/v1/portal/auth/logout`
- `GET /api/v1/portal/auth/me` — current patient profile

#### 2.4 Portal API routes (`/api/v1/portal/*`)
- `GET /api/v1/portal/orders` — patient's order list (PRX-sourced + local shells merged)
- `GET /api/v1/portal/orders/{uuid}` — order detail + shipment tracking
- `GET /api/v1/portal/prescriptions` — prescription list *(requires PRX gap #11)*
- `GET /api/v1/portal/prescriptions/{id}` — prescription detail *(requires PRX gap #11)*
- `GET /api/v1/portal/lab-results` — lab results list *(requires PRX gap #2)*
- `GET /api/v1/portal/subscriptions` — subscription list
- `PATCH /api/v1/portal/subscriptions/{id}` — pause/cancel
- `GET /api/v1/portal/profile` — patient demographics
- `PATCH /api/v1/portal/profile` — update contact info, communication prefs
- `POST /api/v1/portal/data-export` — HIPAA data export request *(queued job)*

---

### Phase 3 — Clinical admin
**Goal:** Admin staff can view all clinical data from PRX inside the Filament admin without logging into PRX directly.

**Dependencies:** Phase 2.1 (PHI foundation). PRX gaps #11, #16 helpful but not all required.

**Deliverables:**

#### 3.1 Patient viewer (Filament resource — PRX-sourced)
- List view: name, email, DOB, status, intake date, assigned provider
- Detail view: demographics, encounter list, active prescriptions, lab summary
- Gated by `view_patient_records` permission
- All data fetched from PRX at view time — not stored locally

#### 3.2 Encounter viewer (Livewire inside Filament)
- Encounter list per patient: type, date, status, outcome
- Encounter detail: all intake answers, provider notes, timeline
- Gated by `view_encounter_details`

#### 3.3 Prescription viewer
- Per-patient prescription history
- Detail: drug, strength, quantity, instructions, refills, prescriber, signed date, next fill
- Gated by `view_prescriptions`
- Requires PRX gap #11

#### 3.4 Lab results viewer
- Per-patient lab order list and result values
- Biomarker display (name, value, unit, reference range, flag)
- PDF link passthrough
- Gated by `view_lab_results`
- Requires PRX gap #2

#### 3.5 Provider queue / wizard review (Livewire)
- Pending encounters queue assigned to in-house providers
- Review intake answers, add clinical notes, approve/reject protocol
- Trigger prescription via PRX API
- Gated by `manage_provider_queue`

---

### Phase 4 — Affiliate + attribution
**Goal:** Track referrals from ambassadors/influencers, calculate commissions, and give affiliates a self-service portal.

**Dependencies:** Phase 1 (leads + orders must exist for conversion events to attach to).

**Deliverables:**

#### 4.1 Data models + migrations
```
affiliates                 name, email, type (individual/company), status, commission_structure (JSON), payout_method, bank_details (encrypted)
referral_codes             code, type (link|coupon), affiliate_id, campaign, discount_amount, discount_type, active, expires_at
referral_clicks            code_id, ip_hash, user_agent, utm_source/medium/campaign/content/term, landing_page, referer, clicked_at
referral_conversions       code_id, affiliate_id, convertible_type, convertible_id, conversion_type (lead|order|subscription), gross_amount, commission_amount, status, converted_at
commissions                affiliate_id, period_start, period_end, total_conversions, total_gross, total_commission, status (pending|approved|paid)
payouts                    affiliate_id, commission_ids (JSON), amount, method, reference, paid_at, notes
```

#### 4.2 Tracking middleware + attribution engine
- `TrackingMiddleware` on public routes: read `?ref=` or cookie, log click, set/refresh cookie
- UTM parameters stored with click + stamped onto Lead at capture
- `AttributionService` resolves which referral code gets credit per conversion
- `CommissionCalculator` reads `Affiliate.commission_structure` and computes earned amount
- Coupon codes applied at checkout → triggers attribution + optional discount

#### 4.3 Affiliate Filament panel (`/affiliates`)
- Separate Filament panel: own guard, own theme, own navigation
- Pages: Dashboard, My Links, My Codes, Conversions, Commissions, Payouts, Profile
- Affiliates manage their own links (generate, deactivate, copy) — no access to any other data

#### 4.4 Admin-side affiliate management (inside `/admin`)
- `AffiliateResource` — approve/reject applications, configure commission structures, view full stats
- `PayoutResource` — review pending commission batches, mark as paid, export CSV
- Coupon code management — create codes, link to affiliates, set discount amounts

#### 4.5 Attribution API endpoints (for React frontend)
- `GET /api/v1/referral/validate?code={code}` — validate a coupon code, return discount info + affiliate meta
- `POST /api/v1/referral/apply` — explicitly apply a referral code to the current cart session

---

### Phase 5 — React patient portal
**Goal:** Patients have a fully branded, mobile-first portal to manage their health journey.

**Dependencies:** Phase 2 API must be complete. PRX gaps #1, #11, #2 should be shipped.

The React portal is a **separate repository** that consumes `/api/v1/portal/*`. It is not part of this Laravel codebase.

**What this repo provides (already exists):**
- All `/api/v1/portal/*` endpoints (Phase 2)
- `GET /api/v1/theme` — CSS custom properties (primary_color, font_display, etc.)
- `GET /api/v1/brand` — site name, logo URL, contact
- Patient Sanctum tokens with scoped abilities

**Portal feature checklist (for the React repo):**
- [ ] Auth: login, logout, session refresh
- [ ] Dashboard: summary of active prescriptions, pending labs, upcoming refills
- [ ] Orders: list + detail + shipment tracking
- [ ] Prescriptions: list + detail (drug, dose, instructions, refills)
- [ ] Lab results: biomarker table + PDF download
- [ ] Subscriptions: view, pause, cancel, product swap
- [ ] Messaging: inbox + compose (requires PRX gap #3)
- [ ] Profile: update demographics, communication preferences
- [ ] Notifications: in-app bell for prescription signed, lab resulted, shipment update
- [ ] HIPAA data export request

---

### Phase 6 — Advanced / deferred
These are out of scope for the initial launch but architecturally accounted for.

#### 6.1 Bedrock AI protocol suggester
- `App\Services\Llm\BedrockClient` + `App\Services\Llm\ProtocolSuggester`
- Same AWS account as PRX; Bedrock has access to PRX formulary embeddings
- Returns structured `{ focus_areas[], recommended_products[], interactions[] }` (not long-form clinical narrative)
- Livewire `SymptomConcierge` component in admin for testing; public API endpoint for the React homepage hero

#### 6.2 Clinical decision trees
- Versioned rules engine: admin authors decision trees in Filament
- Trees evaluate patient responses and recommend encounter types + products
- Pre-screens patients before PRX embed (reduces preclusion rate)

#### 6.3 Provider portal (full white-label)
- Providers use our admin instead of ever seeing the PRX brand
- Requires PRX provider-scoped tokens
- Full encounter review, prescription approval, patient messaging from inside `/admin`

#### 6.4 Multi-tenant (future)
- Current architecture: one database per client deployment
- Future option: shared database with tenant_id scoping (Spatie multi-tenancy)
- Not needed for Phase 1-5; document the migration path when client count justifies it

---

## PRX API gaps — priority queue for the PRX team

Full details in `docs/architecture/PRX_API_GAPS.md`. Summary by build-phase dependency:

| Gap | Needed for | Priority |
|---|---|---|
| #1 Patient token issuance | Phase 2 patient auth | 🔴 Critical |
| #12 Webhook signing secret | Phase 1.3 webhook receiver | 🔴 Critical (security) |
| #13 Idempotency key status lookup | Phase 1.2 checkout reliability | 🔴 Critical |
| #14 Payment reconciliation endpoint | Phase 1.2 local checkout | 🔴 Critical |
| #6 Webhook event type catalog | Phase 1.3 webhook receiver | 🟠 High |
| #11 Prescription list/detail | Phase 2.4 + Phase 3.3 | 🟠 High |
| #15 Patient search by email | Phase 2.2 deduplication | 🟠 High |
| #2 Lab results | Phase 3.4 + Phase 5 portal | 🟠 High |
| #16 Patient encounter history | Phase 3.2 | 🟠 High |
| #17 Order shipment tracking | Phase 2.4 + Phase 5 | 🟠 High |
| #3 Provider messaging | Phase 5 portal | 🟡 Medium |
| #19 Token ability scopes | Phase 2/3 | 🟡 Medium |
| #18 Geographic availability pre-check | Phase 1.2 | 🟡 Medium |

---

## Documentation checklist

Per the project convention, every shipped module needs `user.md` + `dev.md` under `docs/{module}/`.

| Module | user.md | dev.md | openapi.yaml |
|---|---|---|---|
| Auth + Users | ✅ | ✅ | — |
| Settings | ✅ | ✅ | — |
| CMS | ✅ | ✅ | — |
| PrescribeRx | ✅ | ✅ | — |
| API Foundation | ✅ | ✅ | — |
| Catalog | ✅ | ✅ | — |
| Blog | 🔲 | 🔲 | 🔲 |
| Cart | 🔲 | 🔲 | 🔲 |
| Leads | 🔲 | 🔲 | 🔲 |
| Intake schema | 🔲 | 🔲 | 🔲 |
| Merchant Accounts | 🔲 | 🔲 | 🔲 |
| Orders API | — | — | 🔲 |
| Patient Auth | — | — | — |
| Affiliates | — | — | — |

---

## Git workflow

- `main` — production-ready, tagged releases
- `develop` — integration branch, PRs merge here first
- `feature/{name}` — individual feature branches off `develop`
- Commits follow conventional commit format: `feat:`, `fix:`, `chore:`, `docs:`
- Every PR gets a code review before merging to `develop`
- Merge to `main` = deployment event

---

## Environment variables per deploy

All sensitive config lives in `.env` or the Filament settings pages (encrypted in DB). Nothing client-specific in code.

Key per-instance settings managed via admin UI:
- Brand name, domain, logo, colors, fonts
- PRX API token + environment (sandbox/production)
- NMI security key OR Authorize.Net credentials
- AWS Bedrock credentials (when AI module ships)
- Affiliate payout method config

---

## Deployment notes

- Redis DBs: 7 (default), 8 (cache), 9 (queue-long)
- Horizon prefix: `prx_backend_horizon:`
- Reverb port: 8093
- Supervisor processes: `prx-backend-horizon`, `prx-backend-reverb`
- See `docs/deploy/` when that module ships
