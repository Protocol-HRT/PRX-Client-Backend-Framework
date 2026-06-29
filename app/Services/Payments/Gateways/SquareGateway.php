<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\PaymentResult;
use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use App\Models\Payments\MerchantAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Square gateway using the Payments API (raw HTTP — no SDK dependency).
 *
 * Interface amounts are dollar strings (e.g. "29.99"). Square requires integer
 * cents, so they are converted internally. The `gatewayTransactionId` throughout
 * this class is the Square Payment ID (e.g. `nonce:xxx` or `abc123`).
 *
 * Payload keys consumed from $paymentPayload:
 *   - source_id     — Square payment token from Web Payments SDK (`cnon:xxx` or a card-on-file ID)
 *   - customer_id   — optional Square Customer ID for saved-card flows
 *   - note          — optional order note attached to the payment
 *   - reference_id  — optional external reference (e.g. our local order UUID)
 */
final class SquareGateway implements PaymentGatewayInterface
{
    private const PROD_BASE = 'https://connect.squareup.com/v2';

    private const SANDBOX_BASE = 'https://connect.squareupsandbox.com/v2';

    public function sale(
        string $merchantAccountId,
        string $amount,
        string $currency,
        array $paymentPayload,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);

        $body = array_merge(
            $this->paymentBase($ma, $amount, $currency, $paymentPayload),
            ['autocomplete' => true],
        );

        $response = $this->post($ma, 'payments', $body);

        return $this->fromPaymentResponse($response, 'succeeded', $amount, $currency);
    }

    public function authorize(
        string $merchantAccountId,
        string $amount,
        string $currency,
        array $paymentPayload,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);

        $body = array_merge(
            $this->paymentBase($ma, $amount, $currency, $paymentPayload),
            ['autocomplete' => false],
        );

        $response = $this->post($ma, 'payments', $body);

        return $this->fromPaymentResponse($response, 'authorized', $amount, $currency);
    }

    public function capture(
        string $merchantAccountId,
        string $gatewayTransactionId,
        string $amount,
        string $currency,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);
        $response = $this->post($ma, "payments/{$gatewayTransactionId}/complete", []);
        $data = $response->json();
        $payment = $data['payment'] ?? [];

        $success = $response->successful() && ($payment['status'] ?? '') === 'COMPLETED';

        if (! $success) {
            Log::warning('Square capture failed', ['response' => $data]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? 'captured' : 'failed',
            'transactionId' => $payment['id'] ?? $gatewayTransactionId,
            'message' => $success ? 'Square payment captured.' : ($data['errors'][0]['detail'] ?? 'Square capture failed.'),
            'amount' => $amount,
            'currency' => $currency,
            'gatewayProvider' => GatewayProvider::Square,
            'rawData' => $data,
        ]);
    }

    public function refund(
        string $merchantAccountId,
        string $gatewayTransactionId,
        string $amount,
        string $currency,
        array $context,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);

        $body = [
            'idempotency_key' => (string) Str::uuid(),
            'payment_id' => $gatewayTransactionId,
            'amount_money' => [
                'amount' => $this->toCents($amount),
                'currency' => strtoupper($currency),
            ],
        ];

        if (! empty($context['reason'])) {
            $body['reason'] = $context['reason'];
        }

        $response = $this->post($ma, 'refunds', $body);
        $data = $response->json();
        $refund = $data['refund'] ?? [];

        $success = $response->successful() && in_array($refund['status'] ?? '', ['PENDING', 'COMPLETED'], true);

        if (! $success) {
            Log::warning('Square refund failed', ['response' => $data]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? 'refunded' : 'failed',
            'transactionId' => $refund['id'] ?? null,
            'message' => $success ? 'Square refund queued.' : ($data['errors'][0]['detail'] ?? 'Square refund failed.'),
            'amount' => $amount,
            'currency' => $currency,
            'gatewayProvider' => GatewayProvider::Square,
            'rawData' => $data,
        ]);
    }

    public function void(
        string $merchantAccountId,
        string $gatewayTransactionId,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);
        $response = $this->post($ma, "payments/{$gatewayTransactionId}/cancel", []);
        $data = $response->json();
        $payment = $data['payment'] ?? [];

        $success = $response->successful() && ($payment['status'] ?? '') === 'CANCELED';

        if (! $success) {
            Log::warning('Square void failed', ['response' => $data]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? 'voided' : 'failed',
            'transactionId' => $payment['id'] ?? $gatewayTransactionId,
            'message' => $success ? 'Square payment cancelled.' : ($data['errors'][0]['detail'] ?? 'Square void failed.'),
            'gatewayProvider' => GatewayProvider::Square,
            'rawData' => $data,
        ]);
    }

    public function storePaymentMethod(
        string $merchantAccountId,
        string $payableType,
        string $payableId,
        array $paymentPayload,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);

        $sourceId = $paymentPayload['source_id']
            ?? throw new \InvalidArgumentException('source_id is required to vault with Square.');

        // Create a Square Customer first (or reuse passed customer_id).
        $customerId = $paymentPayload['customer_id'] ?? $this->createCustomer($ma, $payableType, $payableId);

        // Create a card on file linked to that customer.
        $cardResponse = $this->post($ma, 'cards', [
            'idempotency_key' => (string) Str::uuid(),
            'source_id' => $sourceId,
            'card' => [
                'customer_id' => $customerId,
            ],
        ]);
        $data = $cardResponse->json();
        $card = $data['card'] ?? [];

        $success = $cardResponse->successful() && ! empty($card['id']);

        if (! $success) {
            Log::warning('Square storePaymentMethod failed', ['response' => $data]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? 'stored' : 'failed',
            'transactionId' => $success ? $customerId : null,
            'message' => $success ? 'Square card on file created.' : ($data['errors'][0]['detail'] ?? 'Square vault failed.'),
            'gatewayProvider' => GatewayProvider::Square,
            'rawData' => $data,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function account(string $merchantAccountId): MerchantAccount
    {
        /** @var MerchantAccount $ma */
        $ma = MerchantAccount::query()->findOrFail($merchantAccountId);

        if (empty($ma->square_access_token) || empty($ma->square_location_id)) {
            throw new \RuntimeException('Square access token and location ID must be configured on this merchant account.');
        }

        return $ma;
    }

    private function baseUrl(MerchantAccount $ma): string
    {
        return $ma->environment === GatewayEnvironment::Sandbox
            ? self::SANDBOX_BASE
            : self::PROD_BASE;
    }

    /** @return array<string, mixed> */
    private function paymentBase(MerchantAccount $ma, string $amount, string $currency, array $payload): array
    {
        $sourceId = $payload['source_id']
            ?? throw new \InvalidArgumentException('source_id is required for Square payments.');

        $body = [
            'idempotency_key' => (string) Str::uuid(),
            'source_id' => $sourceId,
            'location_id' => $ma->square_location_id,
            'amount_money' => [
                'amount' => $this->toCents($amount),
                'currency' => strtoupper($currency),
            ],
        ];

        foreach (['customer_id', 'note', 'reference_id'] as $key) {
            if (! empty($payload[$key])) {
                $body[$key] = $payload[$key];
            }
        }

        return $body;
    }

    private function fromPaymentResponse(Response $response, string $normalizedStatus, string $amount, string $currency): PaymentResult
    {
        $data = $response->json();
        $payment = $data['payment'] ?? [];

        $successStatuses = ['COMPLETED', 'APPROVED'];
        $success = $response->successful() && in_array($payment['status'] ?? '', $successStatuses, true);

        if (! $success) {
            Log::warning('Square payment failed', [
                'status' => $payment['status'] ?? null,
                'errors' => $data['errors'] ?? null,
            ]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? $normalizedStatus : 'failed',
            'transactionId' => $payment['id'] ?? null,
            'message' => $data['errors'][0]['detail'] ?? ($success ? 'Square payment processed.' : 'Square transaction failed.'),
            'amount' => $amount,
            'currency' => $currency,
            'gatewayProvider' => GatewayProvider::Square,
            'rawData' => $data,
        ]);
    }

    private function createCustomer(MerchantAccount $ma, string $payableType, string $payableId): string
    {
        $response = $this->post($ma, 'customers', [
            'idempotency_key' => (string) Str::uuid(),
            'reference_id' => "{$payableType}:{$payableId}",
        ]);

        $data = $response->json();

        if (! $response->successful() || empty($data['customer']['id'])) {
            throw new \RuntimeException('Failed to create Square customer: '.($data['errors'][0]['detail'] ?? 'Unknown error'));
        }

        return $data['customer']['id'];
    }

    private function post(MerchantAccount $ma, string $path, array $body): Response
    {
        return Http::withToken($ma->square_access_token)
            ->withHeader('Square-Version', '2024-01-18')
            ->acceptJson()
            ->post($this->baseUrl($ma).'/'.$path, $body);
    }

    private function toCents(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
