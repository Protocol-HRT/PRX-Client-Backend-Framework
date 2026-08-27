# Knowledge Base — Operator Guide

## Overview

The Knowledge Base holds one **compound** page per peptide or medication: what it is, how it
works, how it is dosed, what the safety picture looks like, and where it stands with the FDA.
Published pages appear on the public site under **Knowledge base**.

Find it in the admin under **Content > Knowledge base**.

**Nothing goes live until it has a regulatory status.** That is the one hard requirement — the
publish switch stays greyed out without it, and even if a page were flagged as published, the
public site would still hide it. The reason is blunt: with no status set, the page shows **no
not-approved notice at all**, so a research compound reads exactly like an approved medicine.

**A clinician reviewer is optional**, and that is deliberate. See
[About the reviewer](#about-the-reviewer) below.

---

## The queue

The list opens on **Needs a status**, with a count badge in the sidebar. That is the point of
the module: the starting library was summarised from your clinical literature, so every page
arrives written but unchecked. Your job is to work down that list.

The other tabs are **Peptides** (the ones the public knowledge base shows by default),
**Published**, and **All**.

The **Needs a regulatory status** filter and the *Needs a status* tab both show the pages that
cannot publish yet. That is the only thing keeping the switch greyed out.

---

## Reviewing a compound

Open a compound and work through the tabs left to right.

### Identity

| Field | What it is |
|---|---|
| Name | How a reader should see it — "Pyridoxine (Vitamin B6)", not "b6". |
| Slug | The page's web address. **Changing it breaks every existing link and loses any search ranking the page has earned.** Change it only if it is actually wrong, and tell whoever runs the site. |
| Tagline | One line under the name, in plain language — what it is *for*, not what it *is*. "Appetite, energy and fat, addressed at once." |
| Brand names | Trade names it is sold under. Shown on the page and searchable. |
| Synonyms | Other names someone might search for, including spellings you don't use. |
| Image | Optional, at the head of the page. |

### Classification

| Field | What it is |
|---|---|
| This compound is a peptide | The knowledge base shows peptides by default. Leave it off for antibiotics, vitamins, topicals and hormones — they stay in the library, but out of the peptide index. Single amino acids (arginine, methionine) are **not** peptides; tripeptides (glutathione, KPV) are. |
| Regulatory status | See the table below. It is printed on the public page. |
| Route | Subcutaneous injection, oral, topical… |
| Compound class | Carried over from the source data, read-only. Not a browse category. |
| Catalog ingredient | Links the page to what the shop sells. Set this and the public page lists the products containing the compound. |
| Evidence ranking | Blank on every imported page. It ranks compounds against a health goal, which is a later feature — leave it alone for now. |

#### Regulatory status

Pick the one that describes the compound **as we supply it** — not the most flattering status
that exists for some product somewhere.

| Status | Use it when |
|---|---|
| FDA approved | An FDA-approved drug product exists and this is it. |
| Investigational | It is in FDA-registered clinical trials. Not approved for sale. |
| Research use only | Supplied for laboratory research. No approved human use. |
| Compounded preparation | Dispensed by a compounding pharmacy, not as an approved product. |
| Dietary supplement | Regulated as a supplement, not as a drug. |
| Marketed without FDA approval | Sold in the US with no approval behind it. |

Some compounds honestly fit two of these — semaglutide is approved *and* widely compounded.
Choose the one that matches what a customer would actually receive from us.

**Anything other than "FDA approved" puts a visible notice at the top of the public page**
saying so, before the article text. That is deliberate: a reader should not have to reach
paragraph nine of the pharmacology to learn that a compound is not an approved medicine.

The starting value on every imported page is a **suggestion**, not a fact we have checked.
Confirming it is part of what you are reviewing.

### Monograph

Seven sections plus a summary. Each is a normal rich-text editor — bold, italic, links,
headings, lists, quotes and **tables**.

| Section | What belongs there |
|---|---|
| Summary | Two or three sentences. Used on the index card and as the page description in search results. |
| Overview | What it is and where it came from. |
| How it works | The mechanism. |
| Dosing | Titration schedules live here. **Most of these carry a table — keep it.** |
| Clinical evidence | What the studies actually found. |
| Safety and side effects | Adverse effects, contraindications, interactions. |
| Pharmacology | Absorption, half-life, metabolism. The most technical section. |
| References | Citations to the primary literature. |

Two things worth knowing about the imported text:

- **Most of it was written for prescribers, not for customers.** *In plain terms* (the
  patient summary) is the exception — it is the one section already written for the person
  considering the compound, and it is what leads on the public page. Where you have time to
  rewrite, rewrite the others toward that voice. Keep the clinical detail underneath rather
  than deleting it; having both is what makes the page worth reading.
- **The references are a large part of why the page is trusted.** Don't thin them out.

Leave a section blank and it simply doesn't appear on the public page.

### Review

| Field | What it is |
|---|---|
| Reviewed by | Optional. A clinician profile — their **name and credentials appear on the public page.** Leave blank unless someone has actually read the page. Manage the list under **Content > Profiles**. |
| Reviewed on | The date shown as "last reviewed" on the page. |
| Review notes | Internal only. What you checked, what you changed, what is still open. Never published. |
| Published | Goes live. Greyed out until a regulatory status is set; the text under it says so. |
| Provenance | Read-only: how many clinical sources the page was summarised from, and by which pipeline. The source count appears on the public page. |

### SEO

Both fields fall back to sensible defaults when blank — the name, and the summary. Fill them
in when you want to say something different from what the page already says.

---

## About the reviewer

**Don't attach a clinician unless one has actually read the page.**

This content is summarised from your own clinical literature — millions of excerpts and white
papers — by a retrieval system. It is not written by one of your providers. Putting a
provider's name on a page they never read is a false claim of clinical review, on medical
content, and it is worse than having no name there.

That is why the system does not require one. If a clinician genuinely reviews a monograph,
attach them and the byline is worth having: it is the strongest signal a health page can
carry, for readers and for search engines alike. If nobody has, leave it blank — the page
instead tells the reader how it was made, which is true.

## What the public page says about itself

Every published page carries a short block near the bottom: how many clinical sources it was
summarised from, how many of those informed the dosing section, and the reference list. When
no clinician is attached, it also says the page hasn't been individually reviewed and is
educational rather than medical advice.

That is a *stronger* position than it might look. The size of your evidence base is something
no competitor can claim, and being straight about how a page was made is what keeps a large
knowledge base from reading as filler — which search engines judge site-wide, and which can
drag your product pages down with it.

---

## Adding a compound by hand

**Content > Knowledge base > New compound.** Everything above applies; you are just supplying
the text yourself. The name is enough to start — the slug fills itself in.

---

## Importing the starting library

A developer runs this once, from the server. It loads the compound library from a data export,
sets names, slugs, the peptide flag and a suggested regulatory status, and merges the handful
of compounds the source listed twice.

**Import is not publishing.** Everything arrives unpublished, which is what the *Needs a
status* queue is showing you. The regulatory status it arrives with is a **suggestion**, not a
checked fact — confirming it is the work.

Re-running the import later updates the text of pages **nobody has reviewed yet** and leaves
reviewed pages alone, so your review work is never overwritten by accident.
