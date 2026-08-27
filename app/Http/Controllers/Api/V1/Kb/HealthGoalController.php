<?php

namespace App\Http\Controllers\Api\V1\Kb;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Kb\HealthGoalResource;
use App\Models\Kb\HealthGoal;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/health-goals
 *
 * The choices the intake quiz offers. Unpaginated on purpose: a goal list a
 * visitor picks from is a page of a dozen or so, and paginating it would make
 * the frontend fetch twice to render one screen.
 *
 * Defaults to `forQuiz()` — active AND offered in intake. `all=1` returns
 * every active goal, for surfaces that name a goal rather than offering it
 * (a knowledge-base page listing what a compound is for, for instance, which
 * must still resolve a goal that has been withdrawn from the quiz).
 */
class HealthGoalController extends ApiController
{
    /**
     * List health goals.
     *
     * @tags Knowledge base
     *
     * @unauthenticated
     */
    #[QueryParameter('all', 'Include goals not offered in the quiz. Default 0.', type: 'boolean', example: false)]
    #[QueryParameter('tree', 'Nest child goals under their parent instead of returning a flat list. Default 0.', type: 'boolean', example: false)]
    public function index(Request $request): JsonResponse
    {
        $tree = $request->boolean('tree');

        $goals = HealthGoal::query()
            ->when($request->boolean('all'), fn ($q) => $q->active(), fn ($q) => $q->forQuiz())
            // A tree returns roots only and nests the rest; a flat list returns
            // everything, children included, because a flat consumer that
            // silently dropped children would show a shorter quiz than the
            // operator configured.
            ->when($tree, fn ($q) => $q->whereNull('parent_id')->with(['children' => fn ($c) => $c->active()]))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return $this->success(
            HealthGoalResource::collection($goals)->toArray($request),
            ['count' => $goals->count()],
        );
    }
}
