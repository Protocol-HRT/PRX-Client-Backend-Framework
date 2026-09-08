<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a response as never-storable. Applied to the patient-portal proxy,
 * every response of which is PHI belonging to exactly one patient.
 *
 * `no-store` is the load-bearing directive — it forbids writing the payload to
 * disk at all, where `no-cache` merely requires revalidation and still permits
 * a copy on disk. `private` is not enough either: it only bars SHARED caches,
 * and the browser cache on a shared or clinic device is precisely where a
 * prescription list should not persist after logout.
 *
 * `X-Robots-Tag` covers the case where a portal URL is pasted somewhere a
 * crawler can reach it — these routes return JSON, so there is no page to carry
 * a meta tag.
 *
 * Set here rather than per-controller because the guarantee has to hold for
 * error responses too: a 403 or a validation failure can echo an id, and a
 * handler that returns early never reaches a header line inside the action.
 */
class NoStorePhiResponse
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
