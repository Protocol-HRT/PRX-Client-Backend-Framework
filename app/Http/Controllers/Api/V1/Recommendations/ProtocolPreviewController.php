<?php

namespace App\Http\Controllers\Api\V1\Recommendations;

use App\Enums\CatalogStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Catalog\PackageResource;
use App\Http\Resources\Api\V1\Catalog\ProductResource;
use App\Models\Kb\HealthGoal;
use App\Services\Recommendations\GoalRecommendationResolver;
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
    public function __construct(private readonly GoalRecommendationResolver $resolver) {}

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

        $resolved = $goals->map(function (HealthGoal $goal) use ($request, $profile): array {
            $result = $this->resolver->resolve($goal, $profile);

            $products = $result['products']->loadMissing('ingredients', 'healthGoals');
            $packages = $result['packages']->loadMissing([
                // Published-only: see CatalogInliner for why an unconstrained
                // nested products load is a content leak, not a detail.
                'products' => fn ($q) => $q->where('products.status', CatalogStatus::Published),
                'products.healthGoals',
                'healthGoals',
                'healthGoalSourceProducts.healthGoals',
            ]);

            return [
                'goal' => [
                    'name' => $goal->name,
                    'slug' => $goal->slug,
                    'prompt' => $goal->prompt ?: $goal->name,
                ],
                'products' => ProductResource::collection($products)->toArray($request),
                'packages' => PackageResource::collection($packages)->toArray($request),

                // Named by the resolver, not inferred here. "restricted" and
                // "unmapped" both render zero products and need completely
                // different copy, and the distinction needs an unfiltered
                // baseline the frontend does not have — see resolve().
                'outcome' => $result['outcome'],
                'excluded_count' => $result['excluded_count'],
            ];
        });

        return $this->success(
            $resolved->all(),
            [
                'goal_count' => $resolved->count(),
                // Whether any filtering was actually applied. The frontend
                // uses this to decide between "based on what you told us" and
                // a neutral heading — claiming personalisation we did not do
                // is worse than not claiming it.
                'filtered' => ! $profile->isEmpty(),
            ],
        );
    }
}
