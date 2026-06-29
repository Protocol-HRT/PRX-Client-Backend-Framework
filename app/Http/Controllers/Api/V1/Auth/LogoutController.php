<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/auth/logout
 *
 * Revokes the current token only (not all tokens for the user).
 * The client should discard its local copy after this call.
 */
class LogoutController extends ApiController
{
    /**
     * Revoke the current Sanctum token.
     *
     * Deletes only the token used in this request. Other active tokens for the same user
     * remain valid. The client should clear its stored token after calling this endpoint.
     *
     * @tags Auth
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(['message' => 'Token revoked.']);
    }
}
