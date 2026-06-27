<?php

namespace App\Data\Payments;

use App\Enums\Payments\GatewayProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Standardized result returned by any PaymentGatewayInterface method.
 * Callers depend on this shape regardless of which gateway processed the transaction.
 */
final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $transactionId,
        public readonly ?string $message,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?GatewayProvider $gatewayProvider = null,
        public readonly ?array $rawData = null,
    ) {}

    public static function from(array $p): self
    {
        $transactionId = $p['transactionId'] ?? $p['transaction_id'] ?? null;

        return new self(
            (bool) ($p['success'] ?? false),
            (string) ($p['status'] ?? 'failed'),
            $transactionId !== null ? (string) $transactionId : null,
            isset($p['message']) ? (string) $p['message'] : null,
            isset($p['amount']) ? (string) $p['amount'] : null,
            isset($p['currency']) ? (string) $p['currency'] : null,
            self::normalizeProvider($p['gatewayProvider'] ?? $p['gateway_provider'] ?? null),
            isset($p['rawData']) ? (array) $p['rawData'] : null,
        );
    }

    public static function failure(string $message, ?GatewayProvider $provider = null): self
    {
        return new self(
            success: false,
            status: 'failed',
            transactionId: null,
            message: $message,
            gatewayProvider: $provider,
        );
    }

    private static function normalizeProvider(null|string|GatewayProvider $value): ?GatewayProvider
    {
        if ($value instanceof GatewayProvider) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $candidate = GatewayProvider::tryFrom($value);

            if (! $candidate) {
                $slug = Str::of($value)->lower()->replace([' ', '-'], '_')->toString();
                $candidate = GatewayProvider::tryFrom($slug);
            }

            if (! $candidate) {
                Log::warning('Unknown gateway provider value', ['value' => $value]);
            }

            return $candidate;
        }

        return null;
    }
}
