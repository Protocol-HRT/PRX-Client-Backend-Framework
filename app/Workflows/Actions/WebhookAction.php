<?php

namespace App\Workflows\Actions;

use App\Workflows\Contracts\WorkflowActionHandler;
use App\Workflows\WorkflowContext;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * POST the run's context to a URL.
 *
 * The escape hatch, and on purpose. Until an install has a first-class
 * integration for its CRM, a webhook reaches anything that speaks HTTP — which is
 * how this ships useful to companies whose stack we have never heard of.
 *
 * ARBITRARY OUTBOUND URLS ARE THE FEATURE HERE, so the usual SSRF advice does not
 * straightforwardly apply: the operator is deliberately naming a third party. The
 * protections that DO apply are that only an authenticated, permissioned admin
 * can write this config, and that the SUBJECT half of the
 * payload carries only registered fields — `toLog()` filters attributes, previous
 * values and changed field NAMES to the allow-list. The `payload` half is whatever
 * scalars the triggering EVENT exposes and is not bounded by it, so an event
 * carrying something sensitive should not be registered as a trigger.
 *
 * Config: {"url": "...", "method": "POST", "headers": {...}, "timeout": 10}
 */
class WebhookAction implements WorkflowActionHandler
{
    public function handle(WorkflowContext $context, array $config): array
    {
        $url = $config['url'] ?? null;

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('The webhook action needs a valid URL.');
        }

        // FILTER_VALIDATE_URL happily accepts ftp:// and file://. Outbound HTTP to
        // a third party is the deliberate feature here; reading the local
        // filesystem is not.
        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            throw new RuntimeException('Webhook URLs must be http or https.');
        }

        $method = strtoupper((string) ($config['method'] ?? 'POST'));

        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            throw new RuntimeException("Unsupported webhook method [{$method}].");
        }

        $headers = is_array($config['headers'] ?? null) ? $config['headers'] : [];
        $timeout = (int) ($config['timeout'] ?? 10);

        $response = Http::withHeaders($headers)
            ->timeout(max(1, min($timeout, 30)))
            ->send($method, $url, ['json' => $context->toLog()]);

        // A non-2xx is a FAILURE, not a curiosity. Recording "we called it" while
        // the far end returned 500 is how a broken integration looks healthy for
        // a month.
        if ($response->failed()) {
            throw new RuntimeException("Webhook returned HTTP {$response->status()}.");
        }

        return [
            'url' => $url,
            'method' => $method,
            'status' => $response->status(),
        ];
    }
}
