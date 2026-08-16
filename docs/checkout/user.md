# Checkout Module — Operator Guide

**Status:** Complete for the Prescribe-Rx path. Local card payment ships with the merchant-accounts milestone.

This guide covers what an admin configures to make checkout work on a deployment, and what visitors see.

---

## What the visitor experiences

1. **Cart drawer** — items, quantities, a "Pairs well with" suggestion strip, and a **Checkout** button.
2. **Checkout page** (`/checkout` on your storefront) — contact details, date of birth (18+), shipping address, and communication consents, with an order summary and suggestions alongside.
3. **Secure intake + payment** — after submitting the form, the visitor lands on the medical-intake page hosted by this platform, which loads your telehealth partner's (Prescribe-Rx) embedded flow. Their name, contact info, and product selections are pre-filled, so they start directly at the clinical questions, then pay inside the embed.

The visitor's details are saved as a **Lead** (Leads section in the admin) the moment they submit the checkout form — even if they abandon the intake afterwards, you keep the contact + consent record and what was in their cart.

---

## Required configuration

### 1. Checkout path — Settings → Billing

**Default checkout provider** decides who collects payment:

| Option | Meaning |
|---|---|
| **Prescribe-Rx (embed handoff)** *(default)* | Payment + clinical intake happen inside the PRX embed. No card data ever touches this app. |
| **Local gateway** | Payment is charged through your configured merchant account (NMI / Authorize.Net / Stripe / Square). *Frontend payment form not yet available.* |

### 2. Prescribe-Rx embed — Settings → Integrations

For the PRX path you must set:

| Field | Where it comes from |
|---|---|
| **Embed code** (`prescribe_rx_embed_code`) | Generated in the Prescribe-Rx admin under **Embed Configs**. Until this is set, the handoff page shows an "Embed code not configured" notice instead of the intake form. |
| **Encounter type ID** | The PRX encounter type this install submits intakes under. |
| **Environment** | `sandbox` while testing, `production` when live. |

### 3. Upsells — Settings → Billing → "Upsells & cross-sells"

| Setting | Effect |
|---|---|
| **Show upsell suggestions** | Turns the suggestion strips in the cart drawer and on the checkout page on/off. |
| **Maximum suggestions** | Caps how many items are suggested (1–12, default 4). |

Suggestions come **only** from the *Pairs With* and *Related* relations you curate on each product/package (Catalog → edit an item → Relations). "Pairs With" items are shown first; nothing is ever suggested that isn't curated, and items already in the visitor's cart are skipped.

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Handoff page says "Embed code not configured" | Paste the embed code from PRX admin → Embed Configs into Settings → Integrations. |
| No upsell strip appears | Either upsells are disabled in Settings → Billing, or the items in the cart have no *Pairs With* / *Related* relations curated. |
| Frontend still shows the old checkout path after changing it | Billing settings clear the config cache automatically on save; the storefront picks the change up on its next page load. Hard-refresh if the browser cached the page itself. |
| Visitor reports being blocked at "Date of birth" | The platform requires visitors to be 18+. This is validated server-side and cannot be disabled. |
