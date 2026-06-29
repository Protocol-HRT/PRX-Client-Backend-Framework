<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Actions\Orders\ReceivePrescribeRxWebhookAction;
use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/webhooks/prescribe-rx
 *
 * Receives order and encounter status update events from Prescribe-Rx.
 *
 * Authentication: HMAC-SHA256 signature in X-PRX-Signature header
 * (validated when PRESCRIBE_RX_WEBHOOK_SECRET is set in env).
 * Until PRX implements signed delivery, the endpoint trusts the payload
 * and relies on the shared secret env variable being absent to skip checks.
 */
class PrescribeRxWebhookController extends ApiController
{
    public function __construct(
        private readonly ReceivePrescribeRxWebhookAction $action
    ) {}

    /**
     * Receive a Prescribe-Rx order or encounter status update.
     *
     * Accepts HMAC-SHA256 signed webhook payloads from Prescribe-Rx. The signature is
     * verified against the `PRESCRIBE_RX_WEBHOOK_SECRET` env variable. In non-production
     * environments without a secret configured, unsigned payloads are accepted. Returns
     * 401 if the signature is invalid.
     *
     * @tags Webhooks
     *
     * @unauthenticated
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('PRX webhook: invalid signature', ['ip' => $request->ip()]);

            return $this->error('Invalid signature.', 401);
        }

        $payload = $request->json()->all();

        Log::info('PRX webhook received', ['event' => $payload['event'] ?? 'unknown']);

        $this->action->execute($payload);

        return response()->json(['message' => 'Accepted.']);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('services.prescribe_rx.webhook_secret');

        if (blank($secret)) {
            if (app()->isProduction()) {
                Log::error('PRX webhook secret not configured — rejecting request in production.');

                return false;
            }

            // Dev/staging only: allow unsigned payloads when no secret is set.
            return true;
        }

        $signature = $request->header('X-PRX-Signature');
        if (blank($signature)) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
