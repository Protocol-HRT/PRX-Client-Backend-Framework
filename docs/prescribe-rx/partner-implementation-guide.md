# PRX Partner Implementation Guide

> **Status:** Superseded by live exchange. See `partner-implementation-RESPONSE-from-prx.md`
> in the shared contract repo (`acappel01/prx-partner-api-contract`) for PRX's authoritative
> response. The corrections below are applied to our codebase; the response doc takes precedence
> on anything that conflicts with this guide.
>
> **Corrections applied 2026-06-28:**
> - `interaction_type` 4 = ASYNC ("Asynchronous"), NOT Video Call. Video Call = int `1`.
> - `PUT /patients/{id}` is already a partial update — only `id` required in body. No 33-field read-merge needed.
> - Patient email filter is `GET /patients?filter[email]=X` (fuzzy LIKE, not exact match).
> - Scheduling IS implemented at `/scheduling/*` (8 routes), not `/telehealth/providers/availability`.
> - `POST /patients/{id}/issue-token` is live — use this to mint per-patient tokens for `/me/patient/*`.
> - Do NOT include bearer tokens in shared docs. The demo token in this guide's original version was revoked.

**From:** prx-backend (white-label API + Filament admin)
**To:** prescribe-rx (clinical platform)
**Purpose:** Specification for endpoints needed to support the white-label patient portal,
plus a record of what was discovered via live API testing on `demo.prescribe-rx.com`.

This document is written for the developer (or AI assistant) on the prescribe-rx side.
It describes exactly what prx-backend expects, what has already been verified working,
and what still needs to be built or fixed on the PRX side.

---

## How prx-backend Uses the PRX API

prx-backend is a **brand-agnostic Laravel API + Filament admin panel** that white-label
telehealth operators deploy as their own patient-facing product. It is not a direct
consumer app — it is a **proxy and orchestration layer** between the client's React
frontend and the PRX clinical platform.

```
React frontend (client-branded)
    ↓  Sanctum bearer token (issued by prx-backend)
prx-backend  ←→  PRX API  (sales-org Sanctum token, server-to-server)
                 demo.prescribe-rx.com/api/v1
```

All PRX calls originate **server-side from prx-backend** using the sales-org token.
Patients never hold a PRX credential. prx-backend authenticates its own patients via
its own Sanctum token system, then proxies scoped requests to PRX on their behalf.

**Current sales-org token in use (sandbox):** `Demo Sales Org LLC`
Token: `1215|wLZ5w4qkpiutJts8zoFg3GnioXAF0PTR3wy09M0e77942db1`

---

## Identity Model: How prx-backend Tracks Patients

PRX has two separate patient identity concepts that we need to understand:

```json
{
  "id":         "019efaad-365a-...",  // patient_chart_id — the CLINICAL RECORD
  "patient_id": "019efaad-3645-...",  // PRX USER ACCOUNT — auth/login identity
}
```

**prx-backend stores both** on its local `patients` table:

| Our column | PRX field | Purpose |
|---|---|---|
| `prx_patient_chart_id` | `id` (chart) | Used to scope all encounter proxying |
| `prx_patient_id` | `patient_id` | Used to link patients who log into PRX directly |

**Canonical identity key on our side is `email`.** We deduplicate by email before
submitting intake to avoid creating duplicate PRX charts for the same person.

### The Dedup Problem We Found in Sandbox

Live data on the demo sandbox shows `test.patient.glp1demo@example.com` with **two
separate chart IDs** but the **same `patient_id`**. This confirms that chart deduplication
can fail in practice.

**Open question for PRX (see section below):** Does `POST /telehealth/intake/unified`
accept an existing `patient_chart_id` so prx-backend can force reuse of an existing
chart for returning patients?

---

## Verified Working Endpoints

These were tested live against `demo.prescribe-rx.com/api/v1` on 2026-06-27 using the
demo sales-org token. Response shapes are from real payloads.

### `GET /telehealth/encounter-types`
Returns all encounter types for the sales org.
```json
{
  "success": true,
  "data": [
    {
      "id": "019d0461-7ee9-...",
      "name": "Female HRT",
      "slug": "female-hrt",
      "description": null,
      "icon": "ti ti-stethoscope",
      "product_class": "Sex Hormones (Female)",
      "product_type": null,
      "is_featured": false,
      "requires_labs": true,
      "interaction_type": "Video Call"
    }
  ]
}
```

### `GET /telehealth/encounter-types/{id}/schema`
Returns the multi-step intake schema for a given encounter type.
Used at checkout time to know which intake fields to collect before the PRX embed.

### `POST /telehealth/intake/unified`
Submits patient intake data and creates an encounter + patient chart.
Returns:
```json
{
  "success": true,
  "data": {
    "encounter_id": "019efaad-366c-...",
    "encounter_number": "ENC-6813203339",
    "patient_chart_id": "019efaad-365a-...",
    "patient_number": "PAT-3556775735",
    "user_id": null,
    "status": "pending_intake",
    "status_label": "Pending Patient Intake",
    "encounter_type": "Female HRT",
    "completeness_score": 40,
    "workflow": {
      "user_created": true,
      "patient_chart_created": true,
      "encounter_created": true,
      "intake_stored": true
    }
  }
}
```
**prx-backend stores `encounter_id` on the `orders` table and `patient_chart_id` on
the `patients` table.**

### `GET /encounters`
Lists encounters for the sales org. Supports `patient_chart_id` query parameter to
scope to a specific patient. Tested and confirmed working:
```
GET /encounters?patient_chart_id=019efaad-365a-...
→ 200, returns paginated list of 25 encounters for that patient
```

Pagination is supported (`per_page`, `page` params).

### `GET /encounters/{encounter_id}`
Returns full encounter detail including `patient_intake_snapshot`,
`provider_intake_snapshot`, `status`, `interaction_type`, `parent_encounter_id`.
Used by prx-backend to display encounter status in the patient portal.

### `GET /patients`
Lists patients for the sales org. Supports `email` query param for search.
Used by prx-backend for deduplication lookup during patient account creation.
```
GET /patients?email=patient@example.com
→ 200, returns array of matching patients
```
**Important:** Returns all matches — multiple records can share the same email if PRX
chart deduplication failed. prx-backend uses the first match by created-at order
as the canonical chart.

### `GET /patients/{patient_chart_id}`
Returns full patient chart including demographics, allergies, conditions, medications.

### `PUT /patients/{patient_chart_id}`
Full-replacement chart update (not PATCH). Requires all 33 fields.
Tested: `PATCH` returns 405; only `PUT` is accepted.
prx-backend reads the current chart, merges changed fields, then PUTs the full payload.

**Known 33 required parameters (from 500 error body):**
`patient_id, client_id, sales_organization_id, patient_source, referring_provider_id,
first_name, last_name, middle_name, dob, mrn, phone, email, ssn_last4, gender,
race, ethnicity, language, marital_status, blood_type, height_inches, weight_lbs,
emergency_contact_name, emergency_contact_phone, emergency_contact_email,
emergency_contact_relationship, drivers_license_number, allergies, conditions,
medications, bio, profile_photo_url, settings, patient_status`
*(truncated in error — please confirm full list or expose a PATCH endpoint)*

---

## Endpoints Needed From PRX

These are the gaps identified from live testing. All returned 404 or 500 on the sandbox.

---

### 1. Video Token — Fix Required (Route Exists, Throws 500)

**Endpoint:** `GET /encounters/{encounter_id}/video-token`
**Current state:** Route is registered. Returns HTTP 500:
```json
{
  "message": "Call to undefined method App\\Services\\Encounter\\VideoCallService::generateToken()",
  "exception": "Error",
  "file": "/var/www/..."
}
```

**What prx-backend expects:**
```json
// 200 OK
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "room_name": "encounter-019efaad-366c",
    "provider": "twilio",          // or "daily", "whereby", etc.
    "expires_at": "2026-06-27T18:30:00Z",
    "sdk_config": {                // optional: any extra config the frontend SDK needs
      "account_sid": "ACxxx",
      "region": "us1"
    }
  }
}
```

**prx-backend usage:** Patient portal calls `GET /api/v1/patient/encounters/{id}/video-token`.
prx-backend verifies the patient owns this encounter (via `patient_chart_id`), then
proxies this call to PRX and returns the token to the React frontend.
The frontend uses the token to join the video room via the appropriate SDK.

**Questions for PRX:**
- Which video provider are you using? (Twilio Video, Daily.co, Whereby, other?)
- Does the token grant the patient role or provider role?
- What is the token TTL? Should prx-backend cache it?

---

### 2. Vitals — New Sub-Resource

**Why needed:** Patient portal "health profile" view. Admin can review vitals history.

**Endpoint: List vitals**
```
GET /patients/{patient_chart_id}/vitals
```
```json
// 200 OK
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "recorded_at": "2026-06-20T10:00:00Z",
      "recorded_by": "patient",        // or "provider"
      "weight_lbs": 180,
      "height_inches": 68,
      "bmi": 27.4,
      "blood_pressure_systolic": 120,
      "blood_pressure_diastolic": 80,
      "heart_rate": 72,
      "temperature_f": 98.6,
      "notes": null
    }
  ]
}
```

**Endpoint: Record vitals**
```
POST /patients/{patient_chart_id}/vitals
```
```json
// Request body
{
  "weight_lbs": 180,
  "height_inches": 68,
  "blood_pressure_systolic": 120,
  "blood_pressure_diastolic": 80,
  "heart_rate": 72,
  "temperature_f": 98.6,
  "notes": "Self-reported by patient via portal"
}
```
```json
// 201 Created
{
  "success": true,
  "data": { /* same shape as list item */ }
}
```

**prx-backend usage:** Patients submit vitals from the portal before a follow-up
encounter. prx-backend POSTs to PRX and caches the result locally for display.

---

### 3. Lab Results — New Sub-Resource

**Why needed:** Patient portal shows lab history. Providers order labs; results flow back.

**Endpoint: List labs**
```
GET /patients/{patient_chart_id}/labs
```
```json
// 200 OK
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "encounter_id": "uuid",
      "ordered_at": "2026-06-01T09:00:00Z",
      "resulted_at": "2026-06-05T14:30:00Z",
      "lab_name": "LabCorp",
      "panel_name": "Comprehensive Metabolic Panel",
      "status": "resulted",          // ordered | pending | resulted | cancelled
      "results": [
        {
          "test_name": "Glucose",
          "value": "95",
          "unit": "mg/dL",
          "reference_range": "70-99",
          "flag": null               // L | H | LL | HH | null
        }
      ],
      "pdf_url": "https://..."       // nullable
    }
  ]
}
```

**Endpoint: List labs for a specific encounter**
```
GET /encounters/{encounter_id}/labs
```
Same shape. Useful for showing labs ordered during a specific visit.

**prx-backend usage:** Read-only from our side. Labs are ordered/resulted entirely
within PRX. We display them in the patient portal and flag critical values.

---

### 4. Encounter Messaging — New Sub-Resource

**Why needed:** Patient-provider async messaging within an encounter context (e.g.,
"Doctor requested more information", "Patient replied with clarification").

**Endpoint: List messages**
```
GET /encounters/{encounter_id}/messages
```
```json
// 200 OK
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "sent_at": "2026-06-27T10:00:00Z",
      "sender_type": "provider",      // patient | provider | system
      "sender_name": "Dr. Smith",
      "body": "Please provide your most recent blood pressure reading.",
      "read_at": null
    }
  ]
}
```

**Endpoint: Send message**
```
POST /encounters/{encounter_id}/messages
```
```json
// Request (sales-org token, patient is identified by context)
{
  "body": "My most recent reading was 118/76.",
  "sender_type": "patient"
}
```
```json
// 201 Created
{
  "success": true,
  "data": { /* same shape as list item */ }
}
```

**prx-backend usage:** The patient portal has a simple message thread per encounter.
prx-backend proxies all reads and writes through the sales-org token, attaching the
`patient_chart_id` to identify the sender. Unread message counts are surfaced in
the patient dashboard.

---

### 5. Intake Deduplication — Chart Reuse Parameter

**Current behavior:** `POST /telehealth/intake/unified` always creates a new patient
chart, even if the same patient (by email) already has a chart in PRX.

**What prx-backend needs:** A way to pass an existing `patient_chart_id` so PRX
links the new encounter to the existing chart instead of creating a duplicate.

**Proposed request addition:**
```json
// POST /telehealth/intake/unified
{
  "sales_organization_id": "...",
  "encounter_type_id": "...",
  "patient_chart_id": "019efaad-365a-...",   // ← NEW optional field
  "tier1_demographics": { /* ... */ },
  "tier2_medical_baseline": { /* ... */ },
  "tier3_encounter_questions": []
}
```

**Expected behavior when `patient_chart_id` is provided:**
- PRX skips chart creation and links the new encounter to the provided chart
- Response `patient_chart_id` echoes back the same ID (no new chart created)
- `workflow.patient_chart_created` = `false`

**prx-backend flow:**
```
Patient submits checkout
    → prx-backend checks: do we have a Patient row with this email?
    → Yes → include patient_chart_id in intake payload → PRX reuses chart
    → No  → omit patient_chart_id → PRX creates new chart → we store it
```

**This is the single most impactful change for data integrity.** Without it, every
returning patient checkout creates a new orphaned chart.

---

### 6. Scheduling (Future — Low Priority)

These were 404 on sandbox. Documenting the expected shape for when PRX is ready.

**Provider availability:**
```
GET /telehealth/providers/availability?encounter_type_id=X&date_from=Y&date_to=Z
```
```json
{
  "success": true,
  "data": [
    {
      "provider_profile_id": "uuid",
      "provider_name": "Dr. Jane Smith",
      "slots": [
        {
          "starts_at": "2026-06-28T09:00:00Z",
          "ends_at": "2026-06-28T09:30:00Z",
          "available": true
        }
      ]
    }
  ]
}
```

**Book appointment:**
```
POST /encounters/{encounter_id}/schedule
```
```json
// Request
{
  "provider_profile_id": "uuid",
  "starts_at": "2026-06-28T09:00:00Z"
}
```
```json
// 200 OK
{
  "success": true,
  "data": {
    "appointment_id": "uuid",
    "provider_name": "Dr. Jane Smith",
    "starts_at": "2026-06-28T09:00:00Z",
    "ends_at": "2026-06-28T09:30:00Z",
    "video_join_url": "https://..."   // if applicable
  }
}
```

---

## Open Questions for PRX

These need answers before prx-backend can finalize the patient portal implementation.

| # | Question | Needed for |
|---|---|---|
| 1 | Does `PUT /patients/{id}` accept a partial body, or is all 33 fields always required? Exposing `PATCH` would be much safer for partial updates. | Patient profile editing |
| 2 | What video provider does `VideoCallService` use? Twilio Video, Daily.co, Whereby, other? | Frontend SDK integration |
| 3 | What is the video token TTL? Should prx-backend re-fetch it on each portal load? | Token caching strategy |
| 4 | Will `POST /telehealth/intake/unified` accept `patient_chart_id` for chart reuse? | Deduplication (critical) |
| 5 | Does PRX do its own email-based deduplication on intake submit, or is chart creation always new? | Deduplication (critical) |
| 6 | `GET /patients?email=X` — is this an exact match or a fuzzy search? In sandbox it returned multiple records for the same email query. | Dedup lookup reliability |
| 7 | Is there a webhook or polling mechanism for encounter status changes? (e.g., provider accepts → encounter moves from `unassigned` to `assigned`) | Real-time status in patient portal |
| 8 | What are the full list of valid `status` values for an encounter, and what transitions are patient-visible vs provider-only? | Patient portal status display |

---

## Encounter Status Values Observed

From sandbox data, these statuses have been observed on encounter records:

| Status | Meaning |
|---|---|
| `unassigned` | Submitted, not yet assigned to a provider |
| `pending_intake` | Awaiting more patient intake data |
| `assigned` | Provider assigned (inferred) |
| `completed` | Encounter closed |

Confirm the full enum and whether any status allows the patient to take action.

---

## `interaction_type` Values

Observed from encounter-types endpoint and encounter records:

| Value (int) | Label | Notes |
|---|---|---|
| `4` | Video Call | Requires video token endpoint |
| `"Video Call"` | Video Call | String form from encounter-types list |

Confirm the full enum. prx-backend uses this to decide whether to surface a video
join button or a simple status view in the patient portal.

---

## MCP / Live Schema Sharing (Optional but Recommended)

If the PRX codebase has `laravel/boost` installed, prx-backend's Claude instance can
connect to PRX's MCP server and read your database schema, route definitions, and
docs directly — **without any ability to modify anything** (Boost MCP is entirely
read-only: schema inspection, SELECT queries, log reading only).

This eliminates the human middle-man for technical coordination while keeping the
human fully in the loop for all actual code reviews and deploys.

To enable:
```bash
# On the PRX server / dev environment
composer require laravel/boost --dev
php artisan boost:install
```

Then share the MCP server URL and prx-backend's Claude can self-serve schema lookups
rather than requiring you to relay questions.

---

## Summary of Priority

| Priority | Item | Blocker for |
|---|---|---|
| 🔴 Critical | `GET /encounters/{id}/video-token` — fix 500 | Video consults |
| 🔴 Critical | Intake `patient_chart_id` reuse param | Data integrity |
| 🟠 High | `GET/POST /patients/{id}/vitals` | Patient health profile |
| 🟠 High | `GET /patients/{id}/labs` + `GET /encounters/{id}/labs` | Lab results view |
| 🟡 Medium | `GET/POST /encounters/{id}/messages` | Patient-provider messaging |
| 🟡 Medium | Answer open questions (video provider, status enum, webhook) | Portal completeness |
| 🟢 Low | Scheduling (`/providers/availability`, `/encounters/{id}/schedule`) | Appointment booking |
| 🟢 Low | `PATCH /patients/{id}` (partial update) | Cleaner profile updates |

---

*Document generated from live API testing on `demo.prescribe-rx.com` on 2026-06-27.*
*All response shapes are from real sandbox payloads unless marked as "Proposed".*
