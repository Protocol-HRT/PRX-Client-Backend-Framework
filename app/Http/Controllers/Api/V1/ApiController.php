<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base controller for all /api/v1 endpoints.
 *
 * Provides typed success/error response helpers so every endpoint
 * returns consistent JSON envelopes:
 *
 *   { "data": { ... }, "meta": { ... } }           ← success
 *   { "message": "...", "errors": { ... } }         ← error (mirrors Laravel validation shape)
 */
abstract class ApiController extends Controller
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    protected function success(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
