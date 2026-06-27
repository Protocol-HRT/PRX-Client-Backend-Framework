<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentResult;

interface PaymentGatewayInterface
{
    /** Authorize + capture in a single step. */
    public function sale(
        string $merchantAccountId,
        string $amount,
        string $currency,
        array $paymentPayload,
    ): PaymentResult;

    /** Create an authorization hold without capturing funds. */
    public function authorize(
        string $merchantAccountId,
        string $amount,
        string $currency,
        array $paymentPayload,
    ): PaymentResult;

    /** Capture a prior authorization. */
    public function capture(
        string $merchantAccountId,
        string $gatewayTransactionId,
        string $amount,
        string $currency,
    ): PaymentResult;

    /** Refund a previously captured or settled transaction. */
    public function refund(
        string $merchantAccountId,
        string $gatewayTransactionId,
        string $amount,
        string $currency,
        array $context,
    ): PaymentResult;

    /** Void an unsettled authorization or transaction. */
    public function void(
        string $merchantAccountId,
        string $gatewayTransactionId,
    ): PaymentResult;

    /** Vault a payment method in the gateway and return the gateway's token/profile ID. */
    public function storePaymentMethod(
        string $merchantAccountId,
        string $payableType,
        string $payableId,
        array $paymentPayload,
    ): PaymentResult;
}
