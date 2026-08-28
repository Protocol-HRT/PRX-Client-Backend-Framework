<?php

namespace App\Observers;

use App\Events\Leads\LeadDispositionChanged;
use App\Models\Lead;
use App\Support\ModelChangeSnapshot;

/**
 * Turns a change of `leads.status` into a domain event.
 *
 * AN OBSERVER RATHER THAN A DISPATCH IN EACH ACTION, deliberately. There are
 * already four places that move a lead (MarkLeadHandedOffAction,
 * MarkLeadCompletedAction, both checkout actions) and the Filament form is a
 * fifth; workflow actions will be a sixth. Every one of those is a place to
 * forget, and a funnel that reacts to four of six transitions is worse than one
 * that reacts to none, because the gap is invisible.
 *
 * Watching the column catches all of them, including writes this codebase has
 * not been written yet.
 */
class LeadObserver
{
    /**
     * Wait for the surrounding transaction to commit before dispatching.
     *
     * Both checkout actions move `status` INSIDE a transaction. Without this, a
     * listener is told about a transition that a later rollback then erases —
     * and since this event is the hook the workflow engine hangs on, that means
     * a CRM push or an SMS for a handoff that never happened. Unfixable
     * downstream: you cannot un-send a text message.
     *
     * LeadCreated solves the same problem by dispatching outside the closure;
     * an observer has no "outside", so it declares it instead.
     */
    public bool $afterCommit = true;

    /**
     * Capture the previous values BEFORE Eloquent syncs them away.
     *
     * Required by `$afterCommit`: the handler below runs from a commit callback,
     * by which point `getOriginal()` returns the NEW value. Without this, every
     * transactional status write — the prescribe-rx checkout handoff included —
     * saw `$from === $to` and dispatched nothing at all, while direct writes
     * worked. See App\Support\ModelChangeSnapshot.
     */
    public function updating(Lead $lead): void
    {
        ModelChangeSnapshot::capture($lead);
    }

    public function updated(Lead $lead): void
    {
        if (! $lead->wasChanged('status')) {
            return;
        }

        $snapshot = ModelChangeSnapshot::read($lead);

        $from = $snapshot['original']['status'] ?? null;
        $to = $lead->status;

        // A no-op write (saving the form without touching the select) does not
        // reach here — wasChanged() is false — but a status set to the same
        // value through a different code path could. A transition to where you
        // already are is not a transition.
        if ($from === $to) {
            return;
        }

        LeadDispositionChanged::dispatch(
            $lead,
            $from === null ? null : (string) $from,
            (string) $to,
        );
    }
}
