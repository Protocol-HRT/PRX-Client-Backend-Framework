<?php

namespace App\Http\Middleware;

use App\Settings\IntegrationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-PrescribeRx-Signature header on inbound webhooks.
 *
 * Expected header format: `sha256=<hex digest>`. Computed as
 * HMAC-SHA256(raw_body, prescribe_rx_webhook_secret). Constant-time compared
 * with hash_equals to dodge timing attacks.
 *
 * Fails closed: 401 if signature missing, mis-formatted, or doesn't match.
 * 503 if no secret is configured (better than silently accepting any payload).
 */
class VerifyPrescribeRxSignature
{
    public function __construct(protected IntegrationSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->settings->prescribe_rx_webhook_secret;
        if (blank($secret)) {
            return response()->json([
                'error' => 'webhook signing secret not configured',
            ], 503);
        }

        $header = $request->headers->get('X-PrescribeRx-Signature');
        if (! $header || ! str_starts_with($header, 'sha256=')) {
            return response()->json(['error' => 'missing or malformed signature'], 401);
        }

        $provided = substr($header, strlen('sha256='));
        $computed = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($computed, $provided)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        return $next($request);
    }
}
