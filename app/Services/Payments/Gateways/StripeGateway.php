<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\PaymentResult;
use App\Enums\Payments\GatewayProvider;
use App\Models\Payments\MerchantAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe gateway using the PaymentIntents API (raw HTTP — no SDK dependency).
 *
 * Interface amounts are dollar strings (e.g. "29.99"). Stripe requires integer
 * cents, so they are converted internally. The `gatewayTransactionId` throughout
 * this class is the Stripe PaymentIntent ID (`pi_xxx`).
 *
 * Payload keys consumed from $paymentPayload:
 *   - payment_method  — Stripe PaymentMethod ID from Stripe.js (`pm_xxx`)
 *   - customer        — optional Stripe Customer ID for saved-card flows
 *   - description     — optional charge description
 *   - metadata        — optional key/value array attached to the intent
 */
final class StripeGateway implements PaymentGatewayInterface
{
    private const BASE = 'https://api.stripe.com/v1';

    public function sale(
        string $merchantAccountId,
        string $amount,
        string $currency,
        array $paymentPayload,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);

        $body = array_merge(
            $this->intentBase($amount, $currency, $paymentPayload),
            ['capture_method' => 'automatic', 'confirm' => 'true'],
        );

        $response = $this->post($ma, 'payment_intents', $body);

        return $this->fromIntentResponse($response, 'succeeded', $amount, $currency);
    }

    public function authorize(
        string $merchantAccountId,
        string $amount,
        string $currency,
        array $paymentPayload,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);

        $body = array_merge(
            $this->intentBase($amount, $currency, $paymentPayload),
            ['capture_method' => 'manual', 'confirm' => 'true'],
        );

        $response = $this->post($ma, 'payment_intents', $body);

        return $this->fromIntentResponse($response, 'authorized', $amount, $currency);
    }

    public function capture(
        string $merchantAccountId,
        string $gatewayTransactionId,
        string $amount,
        string $currency,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);
        $body = ['amount_to_capture' => $this->toCents($amount)];
        $response = $this->post($ma, "payment_intents/{$gatewayTransactionId}/capture", $body);

        return $this->fromIntentResponse($response, 'captured', $amount, $currency);
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
            'payment_intent' => $gatewayTransactionId,
            'amount' => $this->toCents($amount),
        ];

        if (! empty($context['reason'])) {
            $body['reason'] = $context['reason'];
        }

        $response = $this->post($ma, 'refunds', $body);
        $data = $response->json();

        $success = $response->successful() && in_array($data['status'] ?? '', ['succeeded', 'pending'], true);

        if (! $success) {
            Log::warning('Stripe refund failed', ['response' => $data]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? 'refunded' : 'failed',
            'transactionId' => $data['id'] ?? null,
            'message' => $data['failure_reason'] ?? ($success ? 'Stripe refund queued.' : 'Stripe refund failed.'),
            'amount' => $amount,
            'currency' => $currency,
            'gatewayProvider' => GatewayProvider::Stripe,
            'rawData' => $data,
        ]);
    }

    public function void(
        string $merchantAccountId,
        string $gatewayTransactionId,
    ): PaymentResult {
        $ma = $this->account($merchantAccountId);
        $response = $this->post($ma, "payment_intents/{$gatewayTransactionId}/cancel", []);
        $data = $response->json();

        $success = $response->successful() && ($data['status'] ?? '') === 'canceled';

        if (! $success) {
            Log::warning('Stripe void failed', ['response' => $data]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? 'voided' : 'failed',
            'transactionId' => $gatewayTransactionId,
            'message' => $success ? 'Stripe payment intent cancelled.' : ($data['error']['message'] ?? 'Stripe void failed.'),
            'gatewayProvider' => GatewayProvider::Stripe,
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
        $pmId = $paymentPayload['payment_method']
            ?? throw new \InvalidArgumentException('payment_method is required to vault with Stripe.');

        // Create or reuse a customer, then attach the payment method.
        $customerId = $paymentPayload['customer'] ?? $this->createCustomer($ma, $payableType, $payableId);

        $attachResponse = $this->post($ma, "payment_methods/{$pmId}/attach", [
            'customer' => $customerId,
        ]);
        $data = $attachResponse->json();

        $success = $attachResponse->successful() && ! empty($data['id']);

        if (! $success) {
            Log::warning('Stripe storePaymentMethod failed', ['response' => $data]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? 'stored' : 'failed',
            // Return the customer ID so callers can charge it without re-entering card details.
            'transactionId' => $success ? $customerId : null,
            'message' => $success ? 'Stripe payment method vaulted.' : ($data['error']['message'] ?? 'Stripe vault failed.'),
            'gatewayProvider' => GatewayProvider::Stripe,
            'rawData' => $data,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function account(string $merchantAccountId): MerchantAccount
    {
        /** @var MerchantAccount $ma */
        $ma = MerchantAccount::query()->findOrFail($merchantAccountId);

        if (empty($ma->stripe_secret_key)) {
            throw new \RuntimeException('Stripe secret key is not configured on this merchant account.');
        }

        return $ma;
    }

    /** @return array<string, mixed> */
    private function intentBase(string $amount, string $currency, array $payload): array
    {
        $body = [
            'amount' => $this->toCents($amount),
            'currency' => strtolower($currency),
        ];

        foreach (['payment_method', 'customer', 'description'] as $key) {
            if (! empty($payload[$key])) {
                $body[$key] = $payload[$key];
            }
        }

        if (! empty($payload['metadata']) && is_array($payload['metadata'])) {
            foreach ($payload['metadata'] as $k => $v) {
                $body["metadata[{$k}]"] = $v;
            }
        }

        return $body;
    }

    private function fromIntentResponse(Response $response, string $normalizedStatus, string $amount, string $currency): PaymentResult
    {
        $data = $response->json();

        $terminalStatuses = ['succeeded', 'requires_capture', 'canceled'];
        $success = $response->successful()
            && in_array($data['status'] ?? '', [...$terminalStatuses, 'requires_action'], true)
            && ($data['status'] ?? '') !== 'canceled';

        if (! $success) {
            Log::warning('Stripe PaymentIntent failed', [
                'status' => $data['status'] ?? null,
                'error' => $data['error'] ?? null,
            ]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? $normalizedStatus : 'failed',
            'transactionId' => $data['id'] ?? null,
            'message' => $data['error']['message'] ?? ($success ? 'Stripe intent processed.' : 'Stripe transaction failed.'),
            'amount' => $amount,
            'currency' => $currency,
            'gatewayProvider' => GatewayProvider::Stripe,
            'rawData' => $data,
        ]);
    }

    private function createCustomer(MerchantAccount $ma, string $payableType, string $payableId): string
    {
        $response = $this->post($ma, 'customers', [
            'metadata[payable_type]' => $payableType,
            'metadata[payable_id]' => $payableId,
        ]);

        $data = $response->json();

        if (! $response->successful() || empty($data['id'])) {
            throw new \RuntimeException('Failed to create Stripe customer: '.($data['error']['message'] ?? 'Unknown error'));
        }

        return $data['id'];
    }

    private function post(MerchantAccount $ma, string $path, array $body): Response
    {
        return Http::withBasicAuth($ma->stripe_secret_key, '')
            ->asForm()
            ->post(self::BASE.'/'.$path, $body);
    }

    private function toCents(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
