<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/v1/auth/login
 *
 * Issues a Sanctum token scoped to the frontend.
 * Returns the token only once — subsequent calls issue new tokens.
 *
 * Abilities granted:
 *   frontend:*  — standard authenticated user scope
 */
class LoginController extends ApiController
{
    /**
     * Authenticate a user and issue a Sanctum token.
     *
     * Returns a plain-text Bearer token with `frontend:*` abilities. The token is shown
     * only once; the client must persist it. Subsequent logins issue a new token without
     * invalidating existing ones.
     *
     * @tags Auth
     *
     * @unauthenticated
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return $this->error('This account has been deactivated.', 403);
        }

        $deviceName = $validated['device_name'] ?? ($request->userAgent() ?? 'api-client');

        $token = $user->createToken($deviceName, ['frontend:*'])->plainTextToken;

        $user->update(['last_login_at' => now()]);

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], status: 200);
    }
}
