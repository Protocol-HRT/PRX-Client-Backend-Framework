# Workflows — building your funnel

A workflow is one sentence: **when this happens, and these things are true, do that.**

Find them under **Automation → Workflows**.

## When this happens

Two kinds of trigger:

- **When something happens** (recommended) — named moments like *Lead captured*, *Lead moved
  disposition*, *Quiz completed*. These are the ones to reach for.
- **When a record is created / updated / deleted** — fires on any change to that kind of
  record. Broader, and useful when no named event fits.

> *Lead captured* fires for **every** lead, including checkout leads. *Quiz completed* fires
> only for people who finished the quiz. If you want one message for quiz leads and another
> for everyone else, that is two workflows, not one.

## …and all of this is true

Conditions narrow the trigger. Leave them empty and the workflow runs every time.

Each condition picks a field, a comparison, and a value. **All conditions must be true** —
they combine with AND. To express "either/or", build two workflows.

Three kinds of field appear in the list:

| Looks like | Means |
|---|---|
| *Disposition* | the value **now** |
| *Disposition — previous value* | what it was **before** this change |
| *Disposition — was just changed* | put `1` in the value box to mean "this field just moved" |

That is how you write **"moved to Quiz complete, from New"**: one condition on *Disposition
is Quiz complete*, and a second on *Disposition — previous value is New*.

## Then do this

Steps run top to bottom. Drag to reorder.

- **Update a field** — the one that chains your funnel together. Moving a lead to a new
  disposition is itself a trigger, so one workflow's update is the next workflow's start.
- **Send a webhook** — POST the record to any URL. Use this until a proper integration for
  your CRM exists.
- **Run a background job** — dispatch something your installation has made available.

**A failing step does not stop the ones after it**, on purpose: a marketing push that fails
should not prevent the status update behind it. Turn on *Stop the workflow if this fails*
on any step where that is wrong.

## Ordering, and stopping

**Priority** decides which workflow runs first on the same trigger — lower runs first.

**Stop later workflows** means: if this workflow *matches*, the ones after it are skipped.
A workflow that was evaluated and skipped has **not** claimed the trigger, so it never
blocks the ones behind it. This is how you write "route quiz completions here, everything
else to the fallback" without conditions that exclude each other by hand.

## The run log — start here when something seems broken

Open a workflow and look at **Run log**. Every evaluation is listed, **including the ones
that did not match**, because "why didn't my workflow fire?" is the actual question.

| Status | Meaning |
|---|---|
| **Ran** | Matched, and every step succeeded. |
| **Skipped** | The trigger fired but a condition said no. **Why not** names the condition and what the value actually was. |
| **Failed** | It matched and ran, but a step errored. Expand to see which. |

A *Skipped* row reading `email_consent equals "1" — actual: false` is telling you the lead
had not consented — the workflow is working exactly as written.

## Loops are handled, but be aware of them

A workflow that changes a field it also watches would trigger itself forever. The system
stops that automatically: a workflow runs at most once per record per chain, and a chain of
workflows triggering workflows is capped.

If a chain hits that cap it is written to the application log as a warning. That means a
funnel is misconfigured — usually two workflows undoing each other's work.
