<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\PaymentResult;
use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use App\Models\Payments\MerchantAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class NmiGateway implements PaymentGatewayInterface
{
    private const ENDPOINT = 'https://secure.nmi.com/api/transact.php';

    public function sale(string $merchantAccountId, string $amount, string $currency, array $paymentPayload): PaymentResult
    {
        $params = array_merge(
            $this->baseParams($merchantAccountId),
            ['type' => 'sale', 'amount' => $amount, 'currency' => $currency],
            $this->paymentParams($paymentPayload),
        );

        return $this->post($params, 'succeeded', $amount, $currency);
    }

    public function authorize(string $merchantAccountId, string $amount, string $currency, array $paymentPayload): PaymentResult
    {
        $params = array_merge(
            $this->baseParams($merchantAccountId),
            ['type' => 'auth', 'amount' => $amount, 'currency' => $currency],
            $this->paymentParams($paymentPayload),
        );

        return $this->post($params, 'authorized', $amount, $currency);
    }

    public function capture(string $merchantAccountId, string $gatewayTransactionId, string $amount, string $currency): PaymentResult
    {
        $params = array_merge(
            $this->baseParams($merchantAccountId),
            ['type' => 'capture', 'transactionid' => $gatewayTransactionId, 'amount' => $amount],
        );

        return $this->post($params, 'captured', $amount, $currency);
    }

    public function refund(string $merchantAccountId, string $gatewayTransactionId, string $amount, string $currency, array $context): PaymentResult
    {
        $params = array_merge(
            $this->baseParams($merchantAccountId),
            ['type' => 'refund', 'transactionid' => $gatewayTransactionId, 'amount' => $amount],
        );

        return $this->post($params, 'refunded', $amount, $currency);
    }

    public function void(string $merchantAccountId, string $gatewayTransactionId): PaymentResult
    {
        $params = array_merge(
            $this->baseParams($merchantAccountId),
            ['type' => 'void', 'transactionid' => $gatewayTransactionId],
        );

        return $this->post($params, 'voided', null, null);
    }

    public function storePaymentMethod(string $merchantAccountId, string $payableType, string $payableId, array $paymentPayload): PaymentResult
    {
        $params = array_merge(
            $this->baseParams($merchantAccountId),
            [
                'type' => 'sale',
                'amount' => '0.00',
                'customer_vault' => 'add_customer',
            ],
            $this->paymentParams($paymentPayload),
        );

        $response = $this->send($params);

        if (($response['response'] ?? null) === '1') {
            return PaymentResult::from([
                'success' => true,
                'status' => 'stored',
                'transactionId' => $response['customer_vault_id'] ?? null,
                'message' => $response['responsetext'] ?? 'NMI customer vault created.',
                'gatewayProvider' => GatewayProvider::Nmi,
                'rawData' => $response,
            ]);
        }

        Log::warning('NMI storePaymentMethod failed', $response);

        return PaymentResult::from([
            'success' => false,
            'status' => 'failed',
            'transactionId' => null,
            'message' => $response['responsetext'] ?? 'NMI vault creation failed.',
            'gatewayProvider' => GatewayProvider::Nmi,
            'rawData' => $response,
        ]);
    }

    /** @return array<string, string> */
    private function baseParams(string $merchantAccountId): array
    {
        /** @var MerchantAccount $ma */
        $ma = MerchantAccount::query()->findOrFail($merchantAccountId);

        if (empty($ma->nmi_security_key)) {
            throw new \RuntimeException('NMI security key is not configured on this merchant account.');
        }

        return ['security_key' => $ma->nmi_security_key];
    }

    /**
     * Extract payment-specific params from the caller's payload.
     * Supports customer_vault_id (vaulted), or raw ccnumber/ccexp for testing.
     *
     * @return array<string, string>
     */
    private function paymentParams(array $payload): array
    {
        if (! empty($payload['customer_vault_id'])) {
            return ['customer_vault_id' => $payload['customer_vault_id']];
        }

        // Raw card fields — only used in sandbox / test scenarios
        $params = [];
        foreach (['ccnumber', 'ccexp', 'cvv', 'firstname', 'lastname', 'address1', 'city', 'state', 'zip', 'country', 'email', 'phone'] as $field) {
            if (! empty($payload[$field])) {
                $params[$field] = $payload[$field];
            }
        }

        return $params;
    }

    /** @return array<string, mixed> */
    private function send(array $params): array
    {
        $httpResponse = Http::asForm()->post(self::ENDPOINT, $params);
        $body = $httpResponse->body();
        $decoded = [];
        parse_str($body, $decoded);

        return $decoded;
    }

    private function post(array $params, string $normalizedStatus, ?string $amount, ?string $currency): PaymentResult
    {
        try {
            $response = $this->send($params);
        } catch (\Throwable $e) {
            Log::error('NMI HTTP request failed', ['message' => $e->getMessage()]);

            return PaymentResult::failure($e->getMessage(), GatewayProvider::Nmi);
        }

        $success = ($response['response'] ?? null) === '1';
        $transactionId = $response['transactionid'] ?? null;
        $message = $response['responsetext'] ?? ($success ? 'NMI transaction succeeded.' : 'NMI transaction failed.');

        if (! $success) {
            Log::warning('NMI transaction failed', [
                'response_code' => $response['response'] ?? null,
                'response_code_detail' => $response['response_code'] ?? null,
                'message' => $message,
            ]);
        }

        return PaymentResult::from([
            'success' => $success,
            'status' => $success ? $normalizedStatus : 'failed',
            'transactionId' => $transactionId,
            'message' => $message,
            'amount' => $amount,
            'currency' => $currency,
            'gatewayProvider' => GatewayProvider::Nmi,
            'rawData' => $response,
        ]);
    }
}
