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

    /**
     * The `errors` half of the documented envelope above, which this helper
     * could not actually emit until now — so every endpoint that wanted to
     * name a field either built the response by hand or silently dropped it.
     *
     * @param  array<string, mixed>  $errors  field → messages, Laravel's shape
     */
    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
