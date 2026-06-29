# Leads Module — Operator Guide

## Overview

The Leads module captures prospective patients who begin the checkout process on the storefront. A lead is created the moment a visitor submits their contact details and cart selection — before they complete payment or encounter intake.

Leads serve two purposes:

1. **Abandonment recovery** — if a visitor closes the tab before finishing, the frontend can retrieve their lead by UUID and pre-fill the form on their return visit (via a recovery email link, for example).
2. **Audit trail** — every checkout attempt, with its cart snapshot and marketing attribution, is recorded so you can review what products were being considered, where traffic came from, and how far each visitor progressed.

Leads are created exclusively by the storefront. There is no "New Lead" button in the admin — you can only view and edit existing records.

---

## Leads List

Navigate to **Leads** in the admin sidebar to see the full list.

**Columns visible by default:**

| Column | Description |
|---|---|
| Captured | How long ago this lead was created. |
| Status | Color-coded badge: New (gray), Handed off to PRX (yellow), Completed (green), Abandoned (red). |
| Name | First + last name as submitted. Searchable. |
| Email | Email address. Searchable; click the copy icon to copy. |
| Subtotal | Cart subtotal in USD at the time of capture. |
| Checkout path | **Local checkout** (NMI/Authorize.net) or **PRX embed**. |
| SMS / Email | Whether the visitor gave marketing consent for each channel. |
| PRX encounter | UUID of the linked prescribe-rx encounter, if handoff has occurred. |

Several columns (Phone, Checkout path, SMS, Email, UTM source, PRX encounter) can be shown or hidden using the column visibility toggle.

**Filters:**

- **Status** — filter by New, Handed off, Completed, or Abandoned.
- **Checkout path** — filter by Local or PRX embed.
- **Trashed** — show only soft-deleted leads, or all records including deleted.

The list sorts newest first by default.

---

## Lead Status Lifecycle

Each lead moves through these statuses:

| Status | Meaning |
|---|---|
| **New** | Just captured. The visitor has not yet completed checkout. |
| **Handed off to PRX** | The lead was passed to the prescribe-rx embed. The PRX encounter and patient IDs are populated. |
| **Completed** | The full encounter was completed and confirmed in prescribe-rx. |
| **Abandoned** | The visitor did not complete checkout and the lead was marked abandoned (manually or by a future automated process). |

Status moves forward automatically as checkout progresses. Operators can also update the status manually on the edit screen if a correction is needed.

---

## Editing a Lead

Click the edit (pencil) icon on any row to open the lead detail form. Leads are grouped into sections:

### Lead (header fields)

| Field | Description |
|---|---|
| UUID | Read-only internal identifier. Used by the frontend to retrieve this lead. |
| Status | The current stage in the checkout lifecycle. Can be corrected manually. |
| Checkout path | Whether this lead is routed through local checkout or the PRX embed. Set at capture time; shown here for reference. |

### Contact

| Field | Description |
|---|---|
| First name / Last name | As entered by the visitor at intake. Required. |
| Email | Contact email. Required. |
| Phone | Optional phone number. |
| Date of birth | Optional. Required by some encounter types. |

### Address

Street address fields used to pre-fill the PRX embed or local checkout. All optional at capture time.

| Field | Notes |
|---|---|
| Address line 1 / 2 | Street and unit/suite. |
| City | |
| State | 2-letter code, e.g. TX. |
| Postal code | ZIP or postal code. |
| Country | ISO 2-letter code. Defaults to US. |

### Consents

| Field | Description |
|---|---|
| SMS consent | Whether the visitor opted in to SMS marketing. |
| Email consent | Whether the visitor opted in to email marketing. |
| Consent given at | Timestamp when consent was recorded. Populated automatically when either consent toggle is true at capture time. |

> **Note:** These consents are collected locally (on your site) for your own marketing use. Prescribe-rx collects its own separate consents inside its embed for clinical communications.

### Cart snapshot

Shows what the visitor had in their cart at the moment the lead was captured. This is a snapshot — it does not change if the cart is later modified.

| Field | Description |
|---|---|
| Subtotal | Dollar value of the cart at capture time. |
| Item count | Number of items in the cart (computed, read-only). |

### prescribe-rx handoff

These fields are populated automatically when the checkout flow hands the lead off to prescribe-rx.

| Field | Description |
|---|---|
| PRX encounter ID | UUID of the encounter created in prescribe-rx. |
| PRX patient ID | UUID of the patient record in prescribe-rx. |
| Handed off at | When the handoff occurred. |
| Completed at | When the encounter was marked complete. |

### Marketing attribution

Collapsed by default. Contains UTM parameters and referrer/landing page URLs captured from the visitor's browser at lead-creation time. Also includes the IP address and user-agent string recorded for compliance purposes. These fields are read-only in practice — they are set automatically and should not need manual editing.

### Notes

Internal free-text notes visible only to operators. Not shown to the visitor.

---

## Deleting Leads

Leads use **soft delete**: the Delete action on the edit page moves the record to the trash (it is hidden from the default list view but can be restored). Use **Force delete** to permanently remove a record. Use **Restore** to undo a soft delete.

Bulk soft-delete, force-delete, and restore are also available from the list view via the bulk-action menu.

> Use permanent deletion with caution. Deleted leads cannot be recovered.

---

## What Operators Do Not Configure Here

- **Lead capture fields** are defined by the frontend checkout form and the API. You cannot add or remove fields from the admin.
- **Automated status transitions** (New → Handed off → Completed) happen programmatically as checkout progresses. No manual action is required for normal lead lifecycle.
- **Marketing consent handling** (unsubscribe, suppression lists) is outside this module's scope — you would manage that in your email/SMS platform.
