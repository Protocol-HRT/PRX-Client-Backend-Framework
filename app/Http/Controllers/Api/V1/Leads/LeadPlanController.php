<?php

namespace App\Http\Controllers\Api\V1\Leads;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Kb\HealthGoal;
use App\Models\Lead;
use App\Models\Quiz\Quiz;
use App\Services\Mail\MailConfigurator;
use App\Services\Recommendations\ProtocolPresenter;
use App\Services\Recommendations\QuizProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/leads/{uuid}/plan
 *
 * The protocol a visitor was matched to, resolved from the answers their lead
 * already stores. This is what `/plan/{uuid}` on the frontend renders, and what
 * the plan email links back to.
 *
 * A GET, where `POST /protocol/preview` is deliberately a POST, and the two are
 * consistent rather than contradictory. That endpoint takes goals, sex and age
 * as INPUT, so as a GET it would write a health inference about an IP address
 * into every access and proxy log between here and the browser. This one takes
 * an opaque UUID: the query string reveals nothing about the person, and the
 * answers travel only in a response body, which that machinery does not log.
 *
 * THE UUID IS A BEARER CREDENTIAL, exactly as it is on `GET /leads/{uuid}`.
 * Whoever holds it sees the plan, which is the point — it arrived in the
 * visitor's own redirect and in their own email. It is not a secret shared with
 * anyone else, so this route stays unauthenticated and uncached.
 *
 * RECOMPUTED ON EVERY READ, NOT SNAPSHOTTED. The plan reflects the catalogue as
 * it stands now, so a product withdrawn for safety stops being recommended to
 * someone still holding the link rather than persisting in a saved row. The
 * cost is the other half of that trade: an operator editing the catalogue
 * changes what an already-issued link shows. That is the right default while
 * nothing is emailed — see the plan email's own gating — but it is a decision
 * to revisit alongside a PDF, where the artefact and the page could otherwise
 * disagree.
 */
class LeadPlanController extends ApiController
{
    public function __construct(
        private readonly ProtocolPresenter $presenter,
        private readonly MailConfigurator $mail,
    ) {}

    /**
     * The matched protocol for one lead.
     *
     * @tags Leads
     *
     * @unauthenticated
     */
    public function show(Request $request, Lead $lead): JsonResponse
    {
        $profile = QuizProfile::fromLead($lead);

        // Goals are re-read from `health_goals` rather than trusted from the
        // answer blob: a goal withdrawn or deactivated since the quiz was taken
        // must stop being recommended, and `active()` is the one place that is
        // decided. A lead whose goals have all been withdrawn therefore
        // presents identically to one that never answered — both get an empty
        // set, which the frontend reads as "no answers to build a plan from".
        $goals = $profile->goals === []
            ? collect()
            : HealthGoal::query()
                ->whereIn('slug', $profile->goals)
                ->active()
                ->orderBy('position')
                ->get();

        $resolved = $this->presenter->present($goals, $profile->visitor, $request);

        // The results-page words, from the quiz this lead actually took. A
        // lead created at checkout took none, and still lands here from a
        // recovery link — it falls back to the default quiz so the empty state
        // is the operator's sentence rather than a frontend's guess. A null
        // stays null: an unauthored field renders nothing, it does not get a
        // stand-in written in a component.
        $quiz = ($lead->quiz_id !== null ? Quiz::find($lead->quiz_id) : null) ?? Quiz::resolveDefault();

        return $this->success(
            $resolved,
            [
                'goal_count' => count($resolved),

                // Whether the eligibility gate actually had anything to run
                // on. False means nothing was filtered, so a consumer must not
                // claim the plan was personalised — the same rule the preview
                // endpoint states, for the same reason. Part of the documented
                // contract (docs/frontend/dev.md §5a) and emitted for parity
                // with it; the plan page does not branch on it today, because
                // the sentence that would make the claim is the operator's
                // authored intro rather than anything this app composes.
                'filtered' => ! $profile->visitor->isEmpty(),

                // Separates "took the quiz and matched nothing" from "this lead
                // never took the quiz". Both render zero goals and they are not
                // the same thing to say to someone.
                'quiz_completed_at' => $lead->quiz_completed_at?->toIso8601String(),

                // Whether a plan email is genuinely still coming.
                //
                // EVERY CLAUSE IS A REASON A SEND WOULD NEVER ARRIVE, and the
                // flag exists because a page saying "we'll email you shortly"
                // on the strength of a consent tick alone is lying with a green
                // tick. `canSend()` is the same gate the send listener consults
                // (MailConfigurator), so this cannot promise a delivery an
                // operator switched off, or one that would land in a log
                // transport and reach nobody.
                //
                // `quiz_completed_at` is the clause that is easy to miss: the
                // ONLY thing that sends this email is the SendPlanEmail listener
                // on QuizCompleted, which LeadController dispatches solely when
                // a lead arrives carrying a quiz. A lead created at CHECKOUT
                // never fires it — and this endpoint is deliberately reachable
                // for exactly that lead, through a recovery link. Without this
                // clause such a visitor is promised a send that nothing in the
                // system will ever perform.
                //
                // KNOWN GAP, RECORDED RATHER THAN PAPERED OVER: a quiz lead
                // whose listener ran while sending was disabled had its send
                // skipped and logged, and nothing retries it. Once an operator
                // enables mail, this flag reads true for that lead forever.
                // Telling "not sent yet" from "send was skipped" needs a column
                // this change does not add, and re-dispatching on enable would
                // mail every historical lead at once — worse than the stale
                // promise. Close it when the email half is built.
                'email_pending' => $lead->email_consent
                    && filled($lead->email)
                    && $lead->plan_sent_at === null
                    && $lead->quiz_completed_at !== null
                    && $this->mail->canSend(),

                // Operator-authored, keyed by the state it belongs to. The
                // frontend picks by outcome; it never composes a sentence.
                'copy' => $quiz?->resultCopy() ?? [
                    'heading' => null,
                    'intro' => null,
                    'restricted' => null,
                    'unmapped' => null,
                    'empty' => null,
                ],
            ],
        );
    }
}
