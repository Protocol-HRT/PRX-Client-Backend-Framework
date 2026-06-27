<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\PaymentResult;
use App\Enums\Payments\GatewayEnvironment;
use App\Enums\Payments\GatewayProvider;
use App\Models\Payments\MerchantAccount;
use Illuminate\Support\Facades\Log;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

final class AuthorizeNetGateway implements PaymentGatewayInterface
{
    public function sale(string $merchantAccountId, string $amount, string $currency, array $paymentPayload): PaymentResult
    {
        $ctx = $this->ctx($merchantAccountId);

        $trans = new AnetAPI\TransactionRequestType;
        $trans->setTransactionType('authCaptureTransaction');
        $trans->setAmount($amount);
        $this->applyPaymentProfile($trans, $paymentPayload);

        return $this->executeTransaction($ctx, $trans, 'succeeded', $amount, $currency);
    }

    public function authorize(string $merchantAccountId, string $amount, string $currency, array $paymentPayload): PaymentResult
    {
        $ctx = $this->ctx($merchantAccountId);

        $trans = new AnetAPI\TransactionRequestType;
        $trans->setTransactionType('authOnlyTransaction');
        $trans->setAmount($amount);
        $this->applyPaymentProfile($trans, $paymentPayload);

        return $this->executeTransaction($ctx, $trans, 'authorized', $amount, $currency);
    }

    public function capture(string $merchantAccountId, string $gatewayTransactionId, string $amount, string $currency): PaymentResult
    {
        $ctx = $this->ctx($merchantAccountId);

        $trans = new AnetAPI\TransactionRequestType;
        $trans->setTransactionType('priorAuthCaptureTransaction');
        $trans->setRefTransId($gatewayTransactionId);
        $trans->setAmount($amount);

        return $this->executeTransaction($ctx, $trans, 'captured', $amount, $currency);
    }

    public function refund(string $merchantAccountId, string $gatewayTransactionId, string $amount, string $currency, array $context): PaymentResult
    {
        $ctx = $this->ctx($merchantAccountId);

        $trans = new AnetAPI\TransactionRequestType;
        $trans->setTransactionType('refundTransaction');
        $trans->setRefTransId($gatewayTransactionId);
        $trans->setAmount($amount);

        // Authorize.Net requires last 4 of card for refund validation
        if (! empty($context['last_four'])) {
            $creditCard = new AnetAPI\CreditCardType;
            $creditCard->setCardNumber($context['last_four']);
            $creditCard->setExpirationDate('XXXX');
            $payment = new AnetAPI\PaymentType;
            $payment->setCreditCard($creditCard);
            $trans->setPayment($payment);
        }

        return $this->executeTransaction($ctx, $trans, 'refunded', $amount, $currency);
    }

    public function void(string $merchantAccountId, string $gatewayTransactionId): PaymentResult
    {
        $ctx = $this->ctx($merchantAccountId);

        $trans = new AnetAPI\TransactionRequestType;
        $trans->setTransactionType('voidTransaction');
        $trans->setRefTransId($gatewayTransactionId);

        return $this->executeTransaction($ctx, $trans, 'voided', null, null);
    }

    public function storePaymentMethod(string $merchantAccountId, string $payableType, string $payableId, array $paymentPayload): PaymentResult
    {
        $ctx = $this->ctx($merchantAccountId);
        $billing = $paymentPayload['billing'] ?? [];
        $customer = $paymentPayload['customer'] ?? [];

        $billTo = new AnetAPI\CustomerAddressType;
        $billTo->setFirstName($billing['first_name'] ?? 'Cardholder');
        if (! empty($billing['last_name'])) {
            $billTo->setLastName($billing['last_name']);
        }
        $billTo->setAddress($billing['address'] ?? null);
        $billTo->setCity($billing['city'] ?? null);
        $billTo->setState($billing['state'] ?? null);
        $billTo->setZip($billing['zip'] ?? null);
        $billTo->setCountry($billing['country'] ?? 'US');

        $paymentType = $this->buildPaymentType($paymentPayload);
        if ($paymentType === null) {
            return PaymentResult::failure('Opaque card data or payment token is required.', GatewayProvider::AuthorizeNet);
        }

        $paymentProfile = new AnetAPI\CustomerPaymentProfileType;
        $paymentProfile->setPayment($paymentType);
        $paymentProfile->setBillTo($billTo);

        $existingCustomerId = $customer['id'] ?? null;

        try {
            if ($existingCustomerId) {
                return $this->addPaymentProfileToExistingCustomer($ctx, $existingCustomerId, $paymentProfile);
            }

            return $this->createCustomerProfile($ctx, $payableId, $customer, $paymentProfile, $paymentPayload);
        } catch (\Throwable $e) {
            Log::error('Authorize.Net storePaymentMethod exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return PaymentResult::failure($e->getMessage(), GatewayProvider::AuthorizeNet);
        }
    }

    /** @return array{auth: AnetAPI\MerchantAuthenticationType, endpoint: string, validation_mode: string} */
    private function ctx(string $merchantAccountId): array
    {
        /** @var MerchantAccount $ma */
        $ma = MerchantAccount::query()->findOrFail($merchantAccountId);

        if (empty($ma->authnet_api_login_id) || empty($ma->authnet_transaction_key)) {
            throw new \RuntimeException('Authorize.Net credentials are not configured on this merchant account.');
        }

        $auth = new AnetAPI\MerchantAuthenticationType;
        $auth->setName($ma->authnet_api_login_id);
        $auth->setTransactionKey($ma->authnet_transaction_key);

        $sandbox = $ma->environment !== GatewayEnvironment::Production;
        $endpoint = $sandbox ? ANetEnvironment::SANDBOX : ANetEnvironment::PRODUCTION;

        return [
            'auth' => $auth,
            'endpoint' => $endpoint,
            'validation_mode' => $sandbox ? 'testMode' : 'liveMode',
        ];
    }

    private function applyPaymentProfile(AnetAPI\TransactionRequestType $trans, array $payload): void
    {
        $customerId = $payload['gateway_customer_id'] ?? null;
        $profileId = $payload['gateway_payment_profile_id'] ?? null;

        if ($customerId && $profileId) {
            $profile = new AnetAPI\CustomerProfilePaymentType;
            $profile->setCustomerProfileId($customerId);
            $paymentProfile = new AnetAPI\PaymentProfileType;
            $paymentProfile->setPaymentProfileId($profileId);
            $profile->setPaymentProfile($paymentProfile);
            $trans->setProfile($profile);
        }
    }

    private function executeTransaction(array $ctx, AnetAPI\TransactionRequestType $trans, string $normalizedStatus, ?string $amount, ?string $currency): PaymentResult
    {
        $request = new AnetAPI\CreateTransactionRequest;
        $request->setMerchantAuthentication($ctx['auth']);
        $request->setTransactionRequest($trans);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($ctx['endpoint']);

        return $this->resultFromResponse($response, $normalizedStatus, $amount, $currency);
    }

    private function buildPaymentType(array $payload): ?AnetAPI\PaymentType
    {
        $paymentType = new AnetAPI\PaymentType;

        if (! empty($payload['opaque_data'])) {
            $opaque = $payload['opaque_data'];
            $opaqueData = new AnetAPI\OpaqueDataType;
            $opaqueData->setDataDescriptor($opaque['data_descriptor'] ?? 'COMMON.ACCEPT.INAPP.PAYMENT');
            $opaqueData->setDataValue($opaque['data_value'] ?? '');
            $paymentType->setOpaqueData($opaqueData);

            return $paymentType;
        }

        if (! empty($payload['payment_token'])) {
            $opaqueData = new AnetAPI\OpaqueDataType;
            $opaqueData->setDataDescriptor($payload['payment_token']['descriptor'] ?? 'TOKEN');
            $opaqueData->setDataValue($payload['payment_token']['value'] ?? '');
            $paymentType->setOpaqueData($opaqueData);

            return $paymentType;
        }

        return null;
    }

    private function addPaymentProfileToExistingCustomer(array $ctx, string $customerId, AnetAPI\CustomerPaymentProfileType $paymentProfile): PaymentResult
    {
        $request = new AnetAPI\CreateCustomerPaymentProfileRequest;
        $request->setMerchantAuthentication($ctx['auth']);
        $request->setCustomerProfileId($customerId);
        $request->setPaymentProfile($paymentProfile);
        $request->setValidationMode($ctx['validation_mode']);

        $controller = new AnetController\CreateCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($ctx['endpoint']);

        if ($response && $response->getMessages()->getResultCode() === 'Ok') {
            return PaymentResult::from([
                'success' => true,
                'status' => 'stored',
                'transactionId' => $response->getCustomerPaymentProfileId(),
                'message' => 'Authorize.Net payment profile added to existing customer.',
                'gatewayProvider' => GatewayProvider::AuthorizeNet,
                'rawData' => [
                    'customer_id' => $customerId,
                    'payment_profile_id' => $response->getCustomerPaymentProfileId(),
                ],
            ]);
        }

        $error = $response?->getMessages()->getMessage()[0] ?? null;
        Log::error('Authorize.Net add payment profile failed', ['code' => $error?->getCode(), 'message' => $error?->getText()]);

        return PaymentResult::failure($error?->getText() ?? 'Unable to add payment profile.', GatewayProvider::AuthorizeNet);
    }

    private function createCustomerProfile(array $ctx, string $payableId, array $customer, AnetAPI\CustomerPaymentProfileType $paymentProfile, array $paymentPayload): PaymentResult
    {
        $profile = new AnetAPI\CustomerProfileType;
        $profile->setMerchantCustomerId(substr($payableId, 0, 20));
        if (! empty($customer['email'])) {
            $profile->setEmail($customer['email']);
        }
        $profile->setPaymentProfiles([$paymentProfile]);

        $request = new AnetAPI\CreateCustomerProfileRequest;
        $request->setMerchantAuthentication($ctx['auth']);
        $request->setProfile($profile);
        $request->setValidationMode($ctx['validation_mode']);

        $controller = new AnetController\CreateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($ctx['endpoint']);

        if ($response && $response->getMessages()->getResultCode() === 'Ok') {
            return PaymentResult::from([
                'success' => true,
                'status' => 'stored',
                'transactionId' => $response->getCustomerPaymentProfileIdList()[0] ?? null,
                'message' => 'Authorize.Net customer profile created.',
                'gatewayProvider' => GatewayProvider::AuthorizeNet,
                'rawData' => [
                    'customer_id' => $response->getCustomerProfileId(),
                    'payment_profile_id' => $response->getCustomerPaymentProfileIdList()[0] ?? null,
                ],
            ]);
        }

        $messages = $response?->getMessages()->getMessage() ?? [];
        $error = $messages[0] ?? null;

        // Duplicate profile — retry adding to existing customer
        if ($error?->getCode() === 'E00039' && $response?->getCustomerProfileId()) {
            return $this->addPaymentProfileToExistingCustomer(
                $ctx,
                $response->getCustomerProfileId(),
                $paymentProfile,
            );
        }

        Log::error('Authorize.Net create customer profile failed', ['code' => $error?->getCode(), 'message' => $error?->getText()]);

        return PaymentResult::failure($error?->getText() ?? 'Unable to create customer profile.', GatewayProvider::AuthorizeNet);
    }

    private function resultFromResponse(mixed $response, string $normalizedStatus, ?string $amount, ?string $currency): PaymentResult
    {
        $ok = $response && $response->getMessages()->getResultCode() === 'Ok';
        $txn = $response?->getTransactionResponse();
        $transactionId = $txn?->getTransId() ?: null;
        $err = $txn?->getErrors()[0] ?? null;
        $errMsg = $err ? $err->getErrorText().' (code '.$err->getErrorCode().')' : null;

        if (! $ok) {
            Log::warning('Authorize.Net transaction failed', [
                'normalized_status' => $normalizedStatus,
                'message' => $errMsg,
                'transactionId' => $transactionId,
            ]);
        }

        return PaymentResult::from([
            'success' => $ok,
            'status' => $ok ? $normalizedStatus : 'failed',
            'transactionId' => $transactionId,
            'message' => $ok ? 'Authorize.Net transaction succeeded.' : ($errMsg ?? 'Authorize.Net transaction failed.'),
            'amount' => $amount,
            'currency' => $currency,
            'gatewayProvider' => GatewayProvider::AuthorizeNet,
            'rawData' => [
                'resultCode' => $response?->getMessages()?->getResultCode(),
                'transId' => $txn?->getTransId(),
                'authCode' => $txn?->getAuthCode(),
                'avsResult' => $txn?->getAvsResultCode(),
                'errors' => array_map(
                    fn ($e) => ['code' => $e->getErrorCode(), 'text' => $e->getErrorText()],
                    $txn?->getErrors() ?? [],
                ),
            ],
        ]);
    }
}
