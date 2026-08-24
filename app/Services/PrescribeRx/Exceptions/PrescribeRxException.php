<?php

namespace App\Services\PrescribeRx\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Wraps every failure mode of the prescribe-rx HTTP client into one
 * exception type so the Action layer can render a single toast pattern.
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
