# Patient portal proxy

The admin's half of the white-label patient portal. The portal browser talks only to the portal's
own origin; that app talks only to this API; this API talks to the clinical provider. **One
credential boundary, one audit point.** The browser never holds a provider token, and the portal
never holds one either.

Routes live under `/api/v1/patient/*` (`routes/api.php`), behind
`no-store` + `auth:sanctum` + `patient` + `throttle:api`.

## Two rules

**1 · Nothing leaves without passing `PortalResponseFilter`.**

Two provider endpoints return raw Eloquent models — `$paginator->items()` straight into the JSON
envelope, with no `$hidden`, no resource class. The wire payload is the whole table, and the table
grows. Returning what the provider returned ships a live `video_room_token` and the order's
`profit_margin` to a browser.

It is an **allowlist**, not a denylist, and it **fails closed**: a screen with no spec throws
rather than passing the payload through, so forgetting a spec is never the silent default.

**2 · No identifier from the request body is ever forwarded.**

Every id sent onward is read from the session's own `Patient` record, or proven to belong to it
first. This matters most on the two **scheduling** endpoints, which authenticate with the
sales-org token: the provider gates those on the *caller's* tenancy, and the caller is the whole
organisation, so its ownership check cannot tell one of our patients from another. Forwarding a
body straight through there is not a leak — it is a cross-patient **write**.

`storeVital` is the one endpoint that forwards the request body wholesale. It carries no id (the
provider takes the chart from the token and discards unknown keys). Anything that grows an id
there must be validated here first.

## Linking a patient to their chart

An account with no `prx_patient_chart_id` sees nothing: `IssuePortalTokenAction`
refuses to mint without one. Until 2026-09-08 the **only runtime writer of that
column was a text input on the Filament patient form**, so every real patient
needed an operator to paste a chart id by hand. Nothing in checkout, the embed
intake path or the webhooks wrote it, and `leads.patient_id` had an FK and no
writer at all.

`POST /patient/link-chart` (session + `lead_uuid`) closes that, and
`LinkPatientToPrxChartAction` is the single choke point.

### The evidence is the ENCOUNTER. A lead proves nothing.

**The obvious implementation is the vulnerable one, and it was written and
caught in review before it shipped.** That version resolved the chart from the
lead's *email* against the provider. It is a complete account takeover, because
**`POST /leads` is anonymous and returns the uuid** (`routes/api.php:193`,
`LeadResource:20`) — it has to be, it is the checkout form itself:

1. register with the victim's address — nothing verifies it;
2. `POST /leads` with that address, read the uuid out of the 201;
3. claim it — emails match, lead unclaimed, provider resolves the address to the
   victim's chart. Full portal on their clinical record.

So anyone can mint a lead for any address. What cannot be minted is an
**`Encounter`**, whose only two writers take no identifier from an untrusted
caller:

| Writer | Why it is trustworthy |
|---|---|
| `SubmitPrescribeRxCheckoutAction` | our server called the provider and read `patient_chart_id` out of its response |
| `UpsertEncounterAction`, only from `PrescribeRxWebhookController` | HMAC-verified, `hash_equals` on `X-PRX-Signature` |

The chart id comes from there and nowhere else. **`leads.prescribe_rx_patient_id`
is deliberately ignored** — it has four writers, and two of them
(`LeadIntakeController::complete`, `EmbedCompleteController`) take it from the
request body with no credential. A disagreement between it and the encounter is
logged, and the encounter wins.

This design also removed the provider round-trip: the action makes no outbound
call at all.

### Guards

- the lead's email must equal the account's — not the resolution key any more,
  but it keeps a stray uuid from being enough on its own;
- the lead must have an encounter carrying a chart id, or it is refused rather
  than guessed at;
- an account that already has a chart is refused — re-linking would silently
  move a patient's clinical history;
- the lead must be unclaimed. **This is checked twice on purpose**: once up
  front for a clear refusal, once inside the transaction under
  `lockForUpdate()` to close the race. A single-threaded suite cannot tell the
  two apart, so do not read the tests as pinning each separately;
- a chart already held by another account surfaces as a caught
  `UniqueConstraintViolationException`, not a pre-check. The unique index is the
  real arbiter, and **holding a lock to pre-empt it is what must not be done**:
  under `REPEATABLE-READ` a locking read on a unique index for a value that does
  not exist yet takes a **gap lock**, and two unrelated first-time claims then
  deadlock each other on the insert-intention. That regression existed for about
  an hour and is why the check is a `catch`;
- an unknown uuid gives the byte-identical refusal to one belonging to someone
  else, so uuids cannot be enumerated;
- re-claiming the lead you already hold is a no-op, not a 422;
- `prx_chart_verified_at` is stamped **only** here. It means a server-side check
  agreed, which the Filament text input cannot say.

**A patient who checked out under a different address than they registered with
is refused**, and that is the guard working rather than a gap. The Filament
field remains, so an operator can link that case by hand after checking who they
are.

🔴 **Residual, and it must close before real patients use this.**
`patients.email_verified_at` is never set — there is no verification flow — so
the address is asserted, not proven. Someone who both knows a customer's address
**and** holds the uuid of a real, completed, unclaimed order can still claim it.
Requiring the encounter removes an attacker's ability to *manufacture* that
order; it does not remove the value of a leaked one. Patient email verification
is the fix and is not built. Registration deliberately does not link
(`PatientAuthTest` pins that a chart id in the body is ignored), so this endpoint
is the whole surface.

## Endpoints

| Route | Token | Notes |
|---|---|---|
| `POST /patient/link-chart` | **none** | Account, not clinical. Makes no provider call at all — the chart comes from an encounter row. See above. |
| `GET /patient/home` | patient | **Screen-shaped and server-ranked.** One call, not six. |
| `GET /patient/dashboard` | patient | Raw dashboard, filtered. |
| `GET /patient/encounters` | patient | Raw model upstream — heavily filtered. |
| `GET /patient/encounters/{id}/video-token` | patient | Fetched at join time, never at render time. |
| `GET|POST /patient/vitals` | patient | ⚠️ asymmetric field names — see below. |
| `GET /patient/orders` | patient | Raw model upstream — heavily filtered. |
| `GET /patient/prescriptions` | patient | ⚠️ the dose is nested under `items[]`. |
| `GET /patient/conversations` | patient | Polled; real-time is unavailable upstream. |
| `GET|POST /patient/conversations/{id}/messages` | patient | Our field is **`content`**, max 5000. |
| `GET /patient/scheduling/slots` | **sales-org** | Chart id injected from the session. |
| `POST /patient/scheduling/appointments` | **sales-org** | Encounter ownership proven first. |

## Why home is screen-shaped

The obvious way to make a clinical portal fast is to cache the reads. **A cache of clinical reads
is storing PHI**, with a retention policy nobody wrote and a revocation path nobody built — so
that lever is unavailable. The one we do have is **fan-out reduction**: compose the screen here,
close to the provider, rather than making a phone on a bad connection do six sequential
round-trips. Cache the non-PHI half (config, branding) hard; never cache anything patient-specific.

## Why ranking is here and not in the portal

The action-stack tiers, their triggers and the sort are clinical and commercial **policy**, not
presentation (`PatientActionStackService`). Two white-label deployments may legitimately disagree
about what is urgent — one surfaces lab kits aggressively, another does not — and that has to be
configuration rather than a fork of a frontend. Computing it in a browser also makes the policy
unauditable.

Four tiers: `0 Live` · `1 Blocking` · `2 Time-boxed` · `3 Routine`. Sort is tier ascending, then
soonest deadline; no deadline sorts last. **At most one tier-0 task** — the rest are demoted,
because a second live emergency steals the attention the treatment exists to command.

## Upstream failures keep their status

Every provider failure reaches us as one `PrescribeRxException`, and rendering it unhandled made
every one of them a bare `500 {"message":"Server Error"}` — measured on the live sandbox for both
a rejected weight and a provider crash, byte for byte identical. That single body is unusable by
the screen that needs it most.

`POST /patient/vitals` has two failure modes a client absolutely must tell apart:

| Upstream | What happened | What the client must do |
|---|---|---|
| **422** | The patient mistyped a value. Nothing was written. | Name the field. Let them fix it and resubmit. |
| **5xx** | P0-7 — the provider inserts the row and *then* crashes. | **Never invite a retry.** The reading is probably saved; a second one duplicates it. |

So `bootstrap/app.php` renders the exception for `api/*` requests: a 422's field-keyed `errors`
array is passed through, 403/404/409/429 keep their status with a message of ours, and everything
else becomes a **502** (or 503 when the integration is unconfigured, which surfaces as
`httpStatus: 0`).

🔴 **An upstream 401 is deliberately NOT passed through.** By the time the exception escapes,
`withPatientToken()` has already evicted the cached patient token and re-minted one with the
**org** credential, so a second 401 means the provider rejected *our* token — and the visitor is
already authenticated with us or the request never reached a controller. Answering 401 makes every
portal screen say "your session expired"; the patient signs in, that succeeds, and they land on
the same message. Rotating the provider token without updating `IntegrationSettings` would put the
whole portal in that loop. It maps to 502 with the rest of the configuration faults, and
`PortalUpstreamErrorTest` pins it.

The same reasoning applies when **choosing** a status inside `Client`: it is now a status a
patient's screen acts on, so it must describe what the CALLER should do, not where the failure was
detected. `issuePatientToken`'s "response missing token field" was a 422 for that reason and is
now a 502 — the request was fine and the provider answered 2xx with the one field missing.

**What is never passed through is the upstream MESSAGE on a 5xx.** The provider returns its own
stack in those — absolute filesystem paths, and on this endpoint the entire SQL statement with a
`patient_chart_id` inside it. `PortalUpstreamErrorTest` asserts the two outcomes have different
statuses and that none of that string survives.

Filament panel actions are unaffected: they catch the exception themselves and the handler returns
`null` for anything that is not an API request.

## Traps this module has already hit

**`no-store` must be registered before the authenticator in the middleware PRIORITY list**
(`bootstrap/app.php`), not merely listed first on the route. Laravel re-sorts route middleware and
hoists the authenticator regardless of declaration order, which left the header off every 401 and
403 — the responses that echo an id back. The anchor is the **contract**
`Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests`; naming the concrete `Authenticate`
class matches nothing and silently appends to the END of the list.

**Transcribe response specs from the provider's HANDLER, never from its resource DTO.** Several
endpoints have a resource whose field names differ from the array the `/me/patient/*` handler
actually builds. Two specs shipped wrong this way and were caught in review:

- **Vitals is asymmetric.** The request takes `weight_lbs` / `blood_pressure_systolic`; the
  response returns `weight` / `systolic_bp`. Naming the request's fields in the spec stripped
  weight, height and blood pressure out of every reading while the filter reported success.
- **Prescriptions nest.** `sig`, `patient_instructions` and `titration` live under `items[]`. A
  flat spec collapsed each prescription to number and status — the "lead with the answer" rule
  failing at the data layer rather than in the markup.

`PortalFilterFidelityTest` exists because of this: it asserts the fields a screen **needs** survive
the filter. Leak tests alone are only half a contract — an empty allowlist passes every one of them.

**The ownership probe must use the PATIENT token.** `Client::findPatientEncounter` relies on the
provider's own global scope pinning a patient token to its chart, so a foreign encounter 404s.
Switching it to the org token leaves every controller test green while silently reopening the
cross-patient booking hole, so `PortalTokenContractTest` asserts the bearer on the wire. The probe
also writes a PHI-audit row upstream per call, so it belongs on a deliberate action, never a render path.

## Known residual

`provider_profile_id` and `encounter_type_id` are forwarded on the caller's word under the org
token. The provider checks only for double-booking, so a patient could post any existing provider
id — including one outside the org's pool. It is neither a leak nor a cross-patient write, and
closing it costs a second slots call per booking. **Documented rather than fixed**; revisit if the
provider adds pool validation, or if booking moves to the patient token.

## Related

- `docs/prescribe-rx/gap-register.md` — provider-side gaps.
- Frontend runbook: `atlas-protocol-web/docs/runbook/08-prx-api-parity.md` (capability matrix) and
  `09-prx-change-request.md` (the six upstream blockers).
