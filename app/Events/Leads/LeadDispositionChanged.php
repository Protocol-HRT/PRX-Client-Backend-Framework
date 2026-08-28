<?php

namespace App\Events\Leads;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A lead moved from one disposition to another.
 *
 * THE TRIGGER THE FUNNEL IS ACTUALLY BUILT ON. "Quiz completed" fires once, from
 * one code path; "moved to quiz-complete" can be reached from the quiz, from an
 * operator dragging a card, from an import, or from another workflow's
 * UPDATE_FIELD action — and a funnel that only reacts to the first of those is a
 * funnel with invisible holes in it.
 *
 * Carries BOTH slugs because the useful conditions are transitions, not states:
 * "when a lead becomes quiz_complete" is a different rule from "when a lead
 * becomes quiz_complete *from* new", and the second cannot be expressed after
 * the fact — by the time a listener queries the lead, the old value is gone.
 *
 * Fired from LeadObserver on `updated`, so it covers every write path including
 * the Filament form. `$from` is null for a lead whose status was set at
 * creation, which is why creation dispatches LeadCreated instead of a
 * transition from nowhere.
 */
class LeadDispositionChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly ?string $from,
        public readonly string $to,
    ) {}
}
