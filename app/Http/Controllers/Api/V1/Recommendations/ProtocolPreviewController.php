<?php

namespace App\Http\Controllers\Api\V1\Recommendations;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Kb\HealthGoal;
use App\Services\Recommendations\ProtocolPresenter;
use App\Services\Recommendations\VisitorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/protocol/preview
 *
 * What the intake quiz may offer this visitor, for the goals they picked.
 *
 * DELIBERATELY A POST, and the reason is not REST pedantry. As a GET this
 * would put `?goal=sexual-wellness&sex=male&age=62` into every access log,
 * proxy log and analytics row between here and the browser — a health
 * inference about an IP address, written down by default, for a vertical where
 * that is exactly the thing not to do casually. A POST body is not logged by
 * the same machinery. The response is also per-visitor, so it is not cacheable
 * and gains nothing from being a GET.
 *
 * Nothing here is stored. This endpoint answers a question; the answers become
 * a record only when the visitor submits a lead, which is a separate,
 * consented step. Reading a protocol preview must not create a row about
 * someone who then closed the tab.
 *
 * WHAT IS NOT RETURNED: the goal->ingredient weights, and the reason an
 * ingredient was excluded. The first is the mapping a clinician built and a
 * competitor would like; the second would let anyone enumerate which
 * substances are sex- or age-gated by varying the request. `excluded_count`
 * is a count, not a list, and that is the line.
 */
class ProtocolPreviewController extends ApiController
{
    public function __construct(private readonly ProtocolPresenter $presenter) {}

    /**
     * Resolve a protocol preview.
     *
     * @tags Recommendations
     *
     * @unauthenticated
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goals' => ['required', 'array', 'min:1', 'max:10'],
            'goals.*' => ['required', 'string', Rule::exists('health_goals', 'slug')->whereNull('deleted_at')],

            // Free-form by contract, matching leads.gender. An answer that
            // lands in no bucket filters nothing rather than being guessed
            // into one — see SexEligibility::normalize().
            'sex' => ['nullable', 'string', 'max:32'],

            // The quiz slider's range. Enforced here rather than in the
            // column, because a boundary needs a message a person can read.
            'age' => ['nullable', 'integer', 'min:18', 'max:100'],
        ]);

        $profile = new VisitorProfile(
            sex: $validated['sex'] ?? null,
            age: $validated['age'] ?? null,
        );

        $goals = HealthGoal::query()
            ->whereIn('slug', $validated['goals'])
            ->active()
            ->orderBy('position')
            ->get();

        // Shape lives in ProtocolPresenter, shared with the saved-plan
        // endpoint, so the live preview and the report a visitor comes back to
        // can never disagree about the same goal.
        $resolved = $this->presenter->present($goals, $profile, $request);

        return $this->success(
            $resolved,
            [
                'goal_count' => count($resolved),
                // Whether any filtering was actually applied. A consumer must
                // not claim the result was personalised when this is false —
                // claiming personalisation we did not do is worse than not
                // claiming it. Part of the documented contract
                // (docs/frontend/dev.md §5a); no consumer reads it today,
                // because nothing calls this endpoint yet.
                'filtered' => ! $profile->isEmpty(),
            ],
        );
    }
}
