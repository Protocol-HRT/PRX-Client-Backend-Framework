<?php

namespace App\Events\Quiz;

use App\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A visitor finished the intake quiz.
 *
 * An EVENT rather than a direct call from the controller, because this is the
 * hook everything downstream will hang off: the plan email today, and then the
 * CRM push, the SMS, and whatever a workflow's actions add. Each of those is a
 * listener, so none of them can fail the lead creation that produced them —
 * a marketing send must never be the reason a lead is lost.
 */
class QuizCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Lead $lead) {}
}
