<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/auth/me
 *
 * Returns the authenticated user's profile.
 * Lightweight — only fields safe to expose to the React frontend.
 */
class MeController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'last_login_at' => $user->last_login_at?->toISOString(),
        ]);
    }
}
