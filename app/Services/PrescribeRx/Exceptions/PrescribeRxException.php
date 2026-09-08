<?php

namespace App\Services\PrescribeRx\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Wraps every failure mode of the prescribe-rx HTTP client into one
 * exception type.
 *
 * Two consumers, and they want different things from it. The Action layer
 * catches it and renders a single toast pattern in the Filament panels. The
 * API renders it centrally in `bootstrap/app.php`, where `httpStatus` decides
 * the status a portal client sees and `errors` is forwarded on a 422 — so a
 * status set here is a status a patient's screen will act on. Pick it for what
 * the CALLER should do, not for where the failure happened to be detected.
 */
class PrescribeRxException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $errors = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self(
            'PrescribeRx integration is not configured. Set the API token in /admin/settings/integrations.',
            httpStatus: 0,
        );
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json() ?? [];

        $message = $body['message']
            ?? "PrescribeRx API call failed (HTTP {$response->status()})";

        return new self(
            $message,
            httpStatus: $response->status(),
            errors: $body['errors'] ?? null,
        );
    }
}
