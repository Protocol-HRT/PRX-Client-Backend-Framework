# PrescribeRx Integration — User Guide

**Audience:** Site administrators / operations staff. **Permission required:** `super_admin` role until granular Shield permissions are generated for the Integrations settings page.

## What it is

PrescribeRx is the in-house telehealth platform owned by the same client as ProtocolHRT. The unified-intake API lets this site:

- **Submit a new patient + encounter + intake answers** in a single API call (`POST /telehealth/intake/unified`)
- **Fetch the dynamic intake question schema** for any encounter type (`GET /telehealth/encounter-types/{id}/schema`) so the public site's intake wizard can render the right questions per protocol type

## Where it lives in the admin

`/admin/settings/integrations` — under the **Settings** navigation group.

| Field | Notes |
|---|---|
| **Enable integration** | Master switch. When off, the Service throws on every call; useful while you're waiting for a token. |
| **Environment** | `Sandbox` (demo.prescribe-rx.com) for testing, `Production` (prescribe-rx.com) when launched. |
| **API token** | Sales-organization token. Issued from the **production** prescribe-rx admin (works against either environment). Stored encrypted in the DB. Includes the `{id}\|` Sanctum prefix — paste it whole. |
| **Sales organization ID** *(optional)* | UUID. Leave blank to use the org the token already authenticates against. |
| **Client ID** *(optional)* | UUID. Same as above for the client tier. |

## Getting a token

1. Log into the prescribe-rx production admin (the team that issued your sales-org account knows where this is).
2. Generate a sales-organization API token. Tokens have these abilities:
   - `patient:create`, `patient:read`, `patient:update`
   - `encounter:create`, `encounter:read`, `encounter:update`
   - `telehealth:read`, `telehealth:submit`
   - `order:create`, `order:read`, `order:update`
   - `product:read`
   - `webhook:create`, `webhook:read`, `webhook:update`, `webhook:delete`
3. Paste the token into `/admin/settings/integrations` → **API token** and Save.
4. Switch the **Environment** dropdown to `Sandbox` for testing or `Production` to go live.
5. Toggle **Enable integration** on.

## Smoke testing the connection

From a server shell:

```bash
php artisan prescribe-rx:ping
```

What it does:
- Prints the current settings (env, masked token length, stub mode, base URL)
- Calls `GET /telehealth/encounter-types` and reports the count + first few types

To probe the schema for one encounter type:

```bash
php artisan prescribe-rx:ping --type-id=019ce396-46a1-73ab-87d6-c40310555401
```

Expected on success: a green `✓` line + a list of encounter type names. On failure: a red `✗` with the API's error message verbatim.

## What this integration is *NOT* responsible for

- **Local product catalog** — products and packages displayed on protocolhrt.com are **manually curated** in the local CMS (custom images + descriptions). Each product has a `prescribe_rx_product_id` mapping field so when the user adds the product to the cart and submits intake, the right remote product is referenced.
- **Local checkout** — for clients who use NMI / Authorize.net directly, the Payments module owns checkout. This integration only fires if the client routes orders through prescribe-rx.
- **Bedrock LLM protocol generator** — the future "AI-suggested protocol" feature on the homepage hero is a separate integration (AWS Bedrock direct, same AWS account). The prescribe-rx side has its own Bedrock-backed protocol generator but it returns long-form clinical protocols; this site needs a "suggestive" variant. That's deferred work.

## Troubleshooting

- **`PrescribeRx integration is not configured.`** — toggle **Enable integration** on and paste a token.
- **`HTTP 401 Unauthenticated`** — token expired or revoked. Refresh from the prescribe-rx admin and re-paste.
- **`HTTP 403 Forbidden`** — the token's user-type doesn't have the ability the call needs. Sales-org tokens (user type 2) are what this integration expects. If the token came from a different user type (Provider, Patient, etc.) it won't have the right scopes.
- **`HTTP 422` with field-level errors** — the unified-intake payload has invalid keys or values. The API returns an `errors` object listing each invalid field. Common culprits: missing `encounter_type_id`/`slug`/`name`, malformed dates (`YYYY-MM-DD` only), unknown answer keys (must match `field_slugs` from the encounter-type schema).
- **HTML response or 302 redirect** — the `Accept: application/json` header isn't reaching the API. Our client adds it automatically; if you're hitting the API from a custom integration, set it explicitly.
