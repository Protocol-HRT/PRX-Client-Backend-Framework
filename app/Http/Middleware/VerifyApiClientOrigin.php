<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiClientOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->bearerToken();

        if ($rawToken === null) {
            return $next($request);
        }

        $accessToken = PersonalAccessToken::findToken($rawToken);

        if ($accessToken === null || $accessToken->tokenable_type !== ApiClient::class) {
            return $next($request);
        }

        /** @var ApiClient $apiClient */
        $apiClient = $accessToken->tokenable;

        if (! $apiClient->is_active) {
            return response()->json(['message' => 'API client is inactive.'], Response::HTTP_FORBIDDEN);
        }

        $allowedOrigins = $apiClient->allowed_origins ?? [];

        if (! empty($allowedOrigins)) {
            $origin = $request->header('Origin');

            if (! $origin || ! in_array($origin, $allowedOrigins, true)) {
                return response()->json(['message' => 'Origin not allowed.'], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
