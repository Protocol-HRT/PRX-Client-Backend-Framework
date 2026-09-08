# Referrals — operator guide

**Status: live.** Referral tracking, the partner portal and commission
calculation are all in place. See the note under Commissions for the one thing
still missing before money figures appear.

## What it does

When someone sends you traffic — an affiliate, a sales group, a partner — you give
them a link with a code on the end:

```
https://atlasprotocol.com/?ref=LT-4F2A
```

Anyone arriving on that link is recorded, and if they later fill in the quiz or
buy, the sale is credited to whoever owns the code.

## What gets remembered, and for how long

- The code is stored in the visitor's browser for **30 days**. They can browse the
  whole site, leave, come back a fortnight later and buy, and the credit still
  lands.
- **The first code wins.** If a visitor arrives through one affiliate and later
  through another before buying, the first one is credited. Both arrivals are
  recorded, so you can always see what happened.
- **Every arrival is recorded, even on a code that no longer works** — expired,
  switched off, or simply mistyped. This matters: if a partner says "my mailshot
  sent four hundred people and I was paid for none", the answer is in the data
  rather than lost.

## Switching an affiliate off

Set the source to **inactive** and every code they hold stops earning
immediately — you do not have to switch off their links one by one. Their existing
records are untouched, so past commissions can still be reconciled.

## Deleting an affiliate

**You cannot permanently delete an affiliate who has any codes**, and this is on
purpose. Deleting them would destroy the trail behind every sale they were ever
paid for. Deactivate instead. If a record genuinely must go, your developer has to
remove its codes first, which forces the question "are we sure no commission
depends on this?" to be asked out loud.

## What a commission can be argued from

Every referral arrival keeps: the code (stored in lower case, since codes are
not case-sensitive), who owned it at the time, the
page they landed on, where they came from, the date and time, and any campaign
tags on the link. Every referred sale keeps the code and the moment it was
credited.

Crucially, **the code is stored as plain text alongside the links to the affiliate
record.** So even years later, after an affiliate has been removed from the
system, the record still says who was credited. Nothing about referrals is ever
tidied away or expired automatically.

One thing to know: abandoned shopping carts *are* cleared out after 90 days. That
never affects referral data, because none of it is kept on the cart.

## Adding an affiliate

**Leads → Referral sources → New referral source.**

| Field | What it does |
|---|---|
| **Name** | The affiliate, group or partner as you refer to them. |
| **Type** | Affiliate, Sales group, Partner or Internal. Labelling only — it does not change how tracking works. |
| **Slug** | Written onto every referral they produce, so it survives even if you later remove them. Avoid changing it once codes are live. |
| **Reports into** | Who they sit under. Leave blank for a top-level account. |
| **Code prefix** | Codes generated for them start with this, e.g. `acme` gives `acme-7k2f9x`. Defaults to the slug. |
| **Commission rate** | Recorded for your reference. Nothing is calculated automatically yet. |
| **Active** | Switching this off stops every one of their codes earning, immediately. |

Save, and you land on their page with a **Tracking codes** panel.

## Creating a tracking code

On the source's page, **Tracking codes → New code**.

Leave **Code** blank and one is generated for you. Generated codes deliberately
avoid characters people confuse — no `0`/`O`, no `1`/`l`/`I` — because these get
read down the phone and typed off printed flyers, and a commission lost to a
misread letter is an argument nobody can settle. You can type your own code
instead (`SUMMER-2026`); it is stored in lower case, and codes are **not**
case-sensitive, so it does not matter how a partner writes it.

- **Campaign** — your own label. One affiliate can hold several codes and you can
  compare them.
- **Lands on** — a page on the storefront, e.g. `stacks/metabolic-reset`. Leave
  blank for the home page.
- **Expires** — after this, arrivals are still recorded but no longer credited.

The **Link** column gives you the full URL to hand over. Click it to copy.

## QR codes

Every code has a **QR** button. It opens the code as a scannable image with the
link underneath, and **Download SVG** gives you a print-ready file.

It is an SVG rather than a PNG on purpose: SVG scales to any size without going
blurry, so the same file works on a business card and a pull-up banner. Hand it
straight to a printer.

The QR is generated from the link each time — there is nothing to regenerate or
keep in sync. Change where a code lands and the next QR reflects it. But note
that a QR code **already printed** encodes the old link, so treat a printed code
as permanent.

## Sales groups and hierarchy

A source can report into another, as many levels deep as you need. Set it with
**Reports into**.

So if you build this:

```
Main Org 1
├── Main Org 2 ── 3 referral partners
└── Main Org 3 ── 2 referral partners
```

**Main Org 1's numbers include all five partners.** Main Org 2 sees its own three
and nothing of Org 3's branch. Main Org 3 sees its own two. Nobody sees upward:
a partner cannot see their parent group's totals.

Every figure you see on a group is the whole branch beneath it added together —
clicks, leads, conversions and revenue. A group that runs campaigns under its own name sees
that activity included in its own total.

You cannot put a group underneath one of its own sub-accounts; the form will not
offer it and the system refuses it on save.

## People

Open an organisation and use the **People** panel to add someone who can log in
for it. Give them a name, an email and a partner role.

They are sent no password — they set their own through the usual reset link, so
nothing is ever emailed or copied around.

**Anyone attached to an organisation can never reach this staff admin**, whatever
role they are given. That is enforced by the system rather than by permissions, so
it cannot be undone by ticking the wrong box.

Once the partner login is built, these people will see their organisation's
numbers plus everything beneath it, and nothing else.

To remove someone's access, switch them to inactive rather than deleting them —
their history stays intact. And an organisation that still has people attached
cannot be permanently deleted; move or deactivate them first.

## Reading the numbers

The referral sources list shows, per source: how many **codes** they hold, how
many **sub-accounts**, how many **clicks** (with unique visitors beneath), and how
many **leads** resulted. Group figures include the whole branch beneath them. Each
code shows its own clicks on the source's page.

A **conversion** means a payment was actually taken. A lead whose card was
authorised but never charged is not counted — that is money nobody received.

These are counted from the underlying records every time you look, rather than
kept as running totals, so they always agree with the evidence behind them.

## The dashboard

**Your admin dashboard** (the home page) now shows the business at a glance:
leads and conversion, the referral funnel from clicks through to commission owed,
which organisations are producing, revenue and sales over the last 30 days, and
the most recent leads.

## Commissions

> **Nothing will appear here yet on this deployment, and that is not a bug.**
> Commission is earned when a payment is **captured**, and on this install
> prescribe-rx takes the money, not this site (Settings → Billing shows the
> collector as *provider*). Nothing here is told when that happens yet, so
> Conversions, Revenue and Commission owed will read zero until that link is
> built. Clicks and leads are recorded normally in the meantime.

Commission is worked out **the moment a payment is captured**, and written down
permanently.

In a hierarchy, each level earns the difference between its own rate and the rate
already earned beneath it. So with a partner on 10%, their group on 15% and the
national group on 20%, a $100 sale pays **$10 / $5 / $5 — $20 in total, not $45.**
The total is always the highest rate in the chain.

Two things worth knowing:

- **Changing someone's rate never changes what they were already owed.** The rate
  that applied is stored on each commission record, so last quarter's figures stay
  exactly as they were.
- **A commission marked paid is never recalculated.** If something is corrected
  afterwards, pending amounts update but paid ones are left alone — that money has
  already gone out.

A commission is only earned on a payment that was actually **taken**. A card that
was authorised but never charged earns nothing.

## The partner login

Partners sign in at **/partner** — a separate area from this admin.

They see one dashboard, shaped to who they are: a group sees its whole branch
totalled up with a row per sub-organisation, an individual partner sees their own
numbers. Nobody sees another branch, and nobody sees upward.

From there they can add their own sub-organisations and people, and manage their
codes and QR downloads. They **cannot** set commission rates, delete anything, or
reach this staff admin — all three are enforced by the system rather than by
permissions.

## Coming next

- **Being told when a payment succeeds.** This is the one that unblocks the rest:
  until prescribe-rx tells this system a payment was taken, no conversion, revenue
  or commission figure can be anything but zero.
- Approving and paying out commissions — the amounts are tracked, but marking them
  approved or paid is not built yet.
- A statement screen for partners.
- Emailing a new person their invitation (today they use "forgot password").
