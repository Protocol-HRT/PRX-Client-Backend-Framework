<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Content\ProfileResource;
use App\Models\Content\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/profiles
 * GET /api/v1/profiles/{slug}
 */
class ProfileController extends ApiController
{
    /**
     * List published profiles.
     *
     * Returns published profiles ordered by position, with tags. Optionally filter by
     * profile type or restrict to featured profiles only.
     *
     * @tags Content
     *
     * @unauthenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Profile::published()->with('tags')->orderBy('position');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        return ProfileResource::collection($query->get());
    }

    /**
     * Get a published profile by slug.
     *
     * Returns the published profile matching the slug with its tags. Returns 404 if not found
     * or not published.
     *
     * @tags Content
     *
     * @unauthenticated
     */
    public function show(string $slug): JsonResponse
    {
        $profile = Profile::published()->with('tags')->where('slug', $slug)->firstOrFail();

        return $this->success((new ProfileResource($profile))->toArray(request()));
    }
}
