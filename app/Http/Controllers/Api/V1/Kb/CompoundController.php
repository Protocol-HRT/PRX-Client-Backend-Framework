<?php

namespace App\Http\Controllers\Api\V1\Kb;

use App\Enums\Kb\RegulatoryStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Kb\CompoundResource;
use App\Models\Kb\Compound;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/kb/compounds
 * GET /api/v1/kb/compounds/{slug}
 *
 * Both routes serve `Compound::published()` only, which requires a named
 * reviewer AND a regulatory status as well as the published flag — see the
 * model for why each. An incomplete monograph is a 404 here, not a 403: the
 * existence of an unreviewed draft is not public information.
 *
 * `peptides_only` defaults to TRUE. The seed formulary is roughly two thirds
 * antibiotics, topicals and vitamins, and the default answer to "what is in
 * this knowledge base" should be the peptide wiki the frontend is built to be.
 * A caller that wants the whole formulary asks for it.
 */
class CompoundController extends ApiController
{
    /**
     * List published knowledge-base compounds.
     *
     * @tags Knowledge base
     *
     * @unauthenticated
     */
    #[QueryParameter('search', 'Filter by name, tagline or synonym.', type: 'string', example: 'bpc')]
    #[QueryParameter('peptides_only', 'Restrict to peptides. Default true; pass 0 for the full formulary.', type: 'boolean', example: true)]
    #[QueryParameter('regulatory_status', 'Filter by regulatory status.', type: 'string', example: 'research_only')]
    #[QueryParameter('sort', 'Sort order: name (default), -name, newest, oldest.', type: 'string', example: 'name')]
    #[QueryParameter('per_page', 'Results per page (1–100, default 24).', type: 'integer', example: 24)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->integer('per_page', 24), 1), 100);

        $compounds = Compound::query()
            ->published()
            ->with(['reviewedBy', 'ingredient.products'])
            ->when(
                // filled() would treat "0" as present-and-truthy for a boolean
                // toggle; has() plus boolean() is what lets a caller turn the
                // default off.
                ! $request->has('peptides_only') || $request->boolean('peptides_only'),
                fn ($q) => $q->peptides()
            )
            ->when($request->filled('regulatory_status'), function ($q) use ($request): void {
                $status = RegulatoryStatus::tryFrom((string) $request->string('regulatory_status'));

                // An unrecognised status must match nothing rather than being
                // ignored — silently returning the unfiltered list reads to the
                // caller as "no compounds have that status".
                $q->where('regulatory_status', $status?->value ?? '__none__');
            })
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $q->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('tagline', 'like', $term)
                        ->orWhere('synonyms', 'like', $term)
                        ->orWhere('brand_names', 'like', $term);
                });
            })
            ->tap(fn ($q) => $this->applySort($q, (string) $request->string('sort')))
            ->paginate($perPage)
            ->withQueryString();

        return CompoundResource::collection($compounds);
    }

    /**
     * Get a published knowledge-base compound by slug.
     *
     * @tags Knowledge base
     *
     * @unauthenticated
     */
    public function show(Compound $compound): JsonResponse
    {
        // Route-model binding resolves by slug regardless of publication, so
        // the gate has to be re-applied here. Same three conditions as the
        // model's published() scope — keep them in step.
        abort_if(! $compound->is_published || ! $compound->isPublishable(), 404);

        $compound->load(['reviewedBy', 'ingredient.products']);

        return $this->success((new CompoundResource($compound))->toArray(request()));
    }

    /**
     * Name-ascending default: this is an encyclopaedia index, and `position`
     * carries no editorial meaning for 100+ imported rows.
     */
    private function applySort(mixed $query, string $sort): void
    {
        match ($sort) {
            '-name' => $query->orderBy('name', 'desc'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            default => $query->orderBy('name'),
        };
    }
}
