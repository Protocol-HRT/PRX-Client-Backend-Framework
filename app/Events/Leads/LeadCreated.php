<?php

namespace App\Events\Leads;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A lead was captured, by ANY route.
 *
 * Distinct from QuizCompleted, and the distinction is the point. QuizCompleted
 * is guarded by `$quiz !== null` in the controller, so a checkout lead — which
 * is the highest-intent lead this funnel produces — fires nothing at all. A
 * welcome email, an SMS, or a CRM push hung off QuizCompleted would silently
 * skip exactly the people most worth reaching.
 *
 * So this fires from CreateLeadAction, the single choke point every lead passes
 * through, and QuizCompleted stays what it is: the narrower "and they finished
 * the quiz" signal that fires in addition, never instead.
 *
 * Dispatched AFTER the creating transaction commits, not inside it, so a
 * listener can never act on a lead whose insert then rolled back. Listeners
 * should be queued, so nothing downstream can fail the request that produced the
 * lead — there are none yet; the workflow engine will be the first.
 */
class LeadCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Lead $lead) {}
}
