# Workflows — developer reference

"When this happens and these things are true, do that." An operator-configurable
automation engine. Nothing in `app/Workflows` knows what a lead is.

## Why a registry and not enums

This backend is deployed by companies we will never meet, running funnels we cannot
anticipate. An enum of trigger models or action types would mean every one of them forks
the codebase. `App\Workflows\WorkflowRegistry` is a singleton they add to from their own
service provider; the engine, the admin form and the condition builder all follow.

**It is also the security boundary, and that is not a secondary benefit.** Rows in
`workflows` and `workflow_actions` are operator-editable and name what to run. Held as
class names and instantiated, that is arbitrary class instantiation in a product hundreds
of companies deploy. They hold **registry keys**, which resolve only to something the
install deliberately registered.

Three registration surfaces:

```php
$registry->registerSubject('lead', Lead::class, 'Lead', ['status' => 'Disposition', …]);
$registry->registerEvent('lead.created', LeadCreated::class, 'Lead captured', 'lead');
$registry->registerAction('webhook', WebhookAction::class, 'Send a webhook');
$registry->registerJob('sync_crm', SyncCrmJob::class, 'Sync to CRM');   // dispatch allow-list
```

The subject `fields` array is an **allow-list, not documentation**. It bounds what a
condition may read and what an update-field action may write. An unregistered subject
allows nothing — reads fail closed, so a typo'd key never becomes "anything goes".

Atlas's own registrations live in `App\Providers\WorkflowServiceProvider` and are the only
place a domain concept meets the engine.

## Data model

| Table | Holds |
|---|---|
| `workflows` | trigger_type, trigger_target (registry key), `conditions` json, is_active, priority, stop_on_first_match |
| `workflow_actions` | action_type (registry key) + `config` json, sort_order, is_active, halt_on_failure |
| `workflow_runs` | one row per evaluation **including skips**, with `skip_reason` and a `context` snapshot |
| `workflow_action_runs` | one row per step attempted, with `output` or `error` |

`trigger_type` is one of `model_created` / `model_updated` / `model_deleted` /
`event_fired` / `manual`. A string, not an enum column — an install may register a kind
this codebase has never heard of.

## Conditions reuse VisibleWhen — never fork it

`conditions` is the same `[{field, operator, value}, …]` shape the CMS and the quiz already
use, evaluated by `App\Cms\Support\VisibleWhen`. One condition vocabulary for the whole
product, one evaluator to keep correct.

**Change semantics without forking it.** A workflow's most useful conditions are about
change — "became `quiz_complete`", "moved *from* `new`" — which a plain attribute read
cannot express. `WorkflowContext::get()` exposes them as pseudo-fields in the same flat
namespace:

| Field | Reads |
|---|---|
| `status` | the value now |
| `_original.status` | the value before this write |
| `_changed.status` | `'1'` when this write altered it, `''` otherwise |
| `_payload.foo` | a scalar property of the triggering event |

So "moved to quiz_complete from new" is two ordinary conditions and needs no new operators.
`_changed.*` returns a string because VisibleWhen compares via string casts.

Conditions are **ANDed**. There is no OR grouping: two workflows plus `stop_on_first_match`
expresses the same thing, and a boolean tree is a much larger UI than it earns.

## Loop protection — read this before changing the dispatcher

The most useful action is "update a field" and the most useful trigger is "a field
changed". Together they are a cycle, and it is the **first** thing an operator builds.
`WorkflowDispatcher` holds two guards, because they fail differently:

- **Re-entry** — a workflow runs at most once per subject per chain. Catches the direct
  cycle, which is otherwise infinite and writes rows every turn.
- **Depth** (`MAX_DEPTH = 5`) — catches the indirect cycle, A→B→A, which re-entry alone
  would not, since neither workflow repeats for the same subject until the second lap.

The chain is per-process and clears when it drains. A queued listener gets a fresh chain,
which is correct: reacting to a change made by an earlier, finished chain is new causation,
not a loop.

**Re-entry is mutation-tested**; removing it makes the run count 2 instead of 1. **The
depth guard is not.** Re-entry bounds each *workflow* to one acting run, not chain *depth* —
a chain of N distinct workflows on one subject still nests, and enough of them will reach
the cap. It is the backstop for that and for cross-subject actions. Do not remove it on the
grounds that no test covers it.

**The re-entry guard claims the fingerprint BEFORE running and releases it if the run
skipped.** Both halves are load-bearing and each was proven by breaking it: claim after the
run and an action's own write re-enters before the mark lands; hold the claim through a skip
and an ordinary two-stage funnel breaks — workflow A makes workflow B's condition true, and
B, having already been evaluated-and-skipped earlier in the chain, is suppressed and never
fires. That second failure is worse than the loop, because nothing says why.

## Execution

`WorkflowRunner` records **every** evaluation. A skipped run costs a row and answers the
only question operators ask about a workflow that looks broken — so on failure it
re-evaluates one condition at a time to produce a human `skip_reason`
(`status equals "quiz_complete" — actual: "new"`).

Actions run in order. **A failure does not abort by default**: a CRM push that fails must
not prevent the disposition update behind it. `halt_on_failure` is per action.

`WorkflowSubjectObserver` sets `$afterCommit` for the same reason `LeadObserver` does —
several actions move models inside a transaction, and you cannot un-send a webhook.
An update that changed nothing is not a trigger (`getChanges() === []`).

### `_original.*` and `$afterCommit` — do not "simplify" this back

**A commit callback runs after `syncOriginal()`, so `getOriginal()` inside an
`$afterCommit` handler returns the NEW value.** Read it there and `_original.status` equals
the current status, `$from === $to`, and a transition condition can never match. It shipped
that way once: every transactional status write — including the prescribe-rx checkout
handoff, the highest-value transition in the funnel — dispatched **nothing at all**, while
direct writes worked perfectly and the whole suite passed.

`App\Support\ModelChangeSnapshot` captures the previous values at `updating`, before the
sync, into a `WeakMap` keyed by the model. Three properties of it are load-bearing, each
found by breaking it:

- **A WeakMap, not a dynamic property or an `spl_object_id` array.** A dynamic property goes
  through Eloquent's `__set` and becomes a fake attribute that tries to save to a missing
  column; an id-keyed array leaks on rollback and collides after GC reuses ids.
- **Reads are non-destructive.** `Lead` carries two observers; a consuming read starves
  whichever runs second and silently hands it the post-sync values.
- **Captures are idempotent per write, and chain-aware.** Idempotent because those same two
  observers both capture for one write — without the guard the second treats the first as a
  previous write. Chain-aware because in a chain the sync may not have run yet: with a real
  surrounding transaction it has by commit time, but on the non-transactional path an
  "afterCommit" handler runs inline inside `performUpdate`, before `finishSave()` syncs. The
  merge (`original` + `applied` from the previous capture) is correct either way, which is
  why it is unconditional.

A known limit, named so it is not rediscovered: a `saveQuietly()` / `withoutEvents()` /
query-builder write landing between two observed writes on the same instance is invisible to
this, so the merged `original` describes the state before it. Nothing does that to a
registered subject today.

`getChanges()` is unaffected by any of this and stays authoritative for *what* moved; only
the previous *values* needed rescuing.

Pinned by `test_original_survives_a_write_inside_a_transaction`,
`test_original_is_per_write_and_not_inherited_by_a_chained_one`, and (for the event)
`test_the_event_fires_for_a_write_inside_a_transaction`. All three fail if the observers go
back to `getOriginal()`.

## Shipped actions

| Type | Config | Notes |
|---|---|---|
| `update_field` | `{field, value}` | Bounded by the subject allow-list. Throws on an unregistered field rather than silently ignoring it. |
| `webhook` | `{url, method?, headers?, timeout?}` | Non-2xx is a **failure**. Payload is `context->toLog()`, so it carries only registered fields. |
| `dispatch_job` | `{job}` | Registry key only. The most dangerous action, hence its own allow-list. |

**Deliberately absent: any vendor.** Klaviyo/GHL/Twilio arrive as configured integration
instances behind one generic `push_to_integration` action, so the shipped set never names a
third party. See the next-session notes.

## Known gaps

- **The whole chain runs synchronously inside the triggering request.** A webhook on
  `lead.created` stalls the public `POST /api/v1/leads` until it returns (capped at 30s per
  hook). `LeadCreated`'s own doc comment anticipates queued listeners; making the dispatcher
  queue its runs is the fix, and it is not done. Keep webhook timeouts low until it is.
- Action config is a generic key/value editor. A per-action-type schema is the obvious next
  improvement; not required for correctness.
- No `scheduled` or `manual` trigger runner yet — the columns accept them, nothing fires them.
- No shared Filament condition-builder component; `WorkflowForm`, `FlexibleSectionTypeForm`
  and `StepsRelationManager` each build their own repeater over the same shape. Worth
  extracting.
