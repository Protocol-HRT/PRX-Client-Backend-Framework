<?php

namespace App\Http\Controllers\Api\V1\Quiz;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Quiz\Quiz;
use App\Services\Quiz\QuizSchemaBuilder;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/quiz          — the default quiz
 * GET /api/v1/quiz/{slug}   — a named one
 *
 * The QUESTIONS, not the answers. This is public content: it is what a
 * visitor is about to be shown, it is identical for everyone, and it is
 * cacheable — the opposite of `POST /protocol/preview`, which is per-visitor
 * and deliberately uncached.
 *
 * Prices inside it are computed live rather than authored, so the cache tag
 * has to be invalidated by catalog writes as well as quiz writes. See
 * FrontendRevalidator.
 */
class QuizController extends ApiController
{
    public function __construct(private readonly QuizSchemaBuilder $builder) {}

    /**
     * The intake quiz definition.
     *
     * @tags Quiz
     *
     * @unauthenticated
     */
    public function show(?string $slug = null): JsonResponse
    {
        $quiz = $slug === null
            ? Quiz::resolveDefault()
            : Quiz::query()->active()->where('slug', $slug)->first();

        if ($quiz === null) {
            // 404 rather than an empty schema: a frontend that received
            // `steps: []` would render a wizard with no questions and a
            // working Continue button, which looks like a broken quiz rather
            // than an absent one.
            return $this->error('No quiz is configured.', 404);
        }

        return $this->success($this->builder->build($quiz));
    }
}
