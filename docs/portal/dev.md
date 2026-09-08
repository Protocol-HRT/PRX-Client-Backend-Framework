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

## Endpoints

| Route | Token | Notes |
|---|---|---|
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
