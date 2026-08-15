<?php

namespace App\Actions\Checkout;

use App\Actions\Concerns\Transacts;
use App\Data\Checkout\CheckoutResultData;
use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Models\Commerce\Cart;
use App\Models\Commerce\Order;
use App\Models\Lead;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubmitLocalCheckoutAction
{
    use Transacts;

    public function __construct(private readonly PaymentGatewayManager $gatewayManager) {}

    /**
     * Charge the cart total through the default merchant account, then create
     * the local Order shell and mark the lead completed.
     *
     * The gateway charge happens *before* the DB transaction so a rollback
     * can never produce a captured payment with no order record.
     *
     * @param  array<string, mixed>  $paymentMethod  Tokenized payment data from the frontend SDK.
     *
     * @throws RuntimeException when the cart is empty, no gateway is configured, or payment fails.
     */
    public function execute(Cart $cart, Lead $lead, array $paymentMethod): CheckoutResultData
    {
        $items = $cart->items()->with('itemable')->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Cart is empty.');
        }

        $account = $this->gatewayManager->defaultAccount();
        $gateway = $this->gatewayManager->forAccount($account);

        $amount = number_format((float) $cart->subtotal(), 2, '.', '');

        // Charge outside the transaction — a DB rollback must not orphan a real capture.
        $paymentResult = $gateway->sale(
            (string) $account->id,
            $amount,
            'USD',
            $paymentMethod,
        );

        if (! $paymentResult->success) {
            throw new RuntimeException($paymentResult->message ?? 'Payment could not be processed.');
        }

        $order = $this->tx(function () use ($cart, $lead, $items, $paymentResult, $account, $amount): Order {
            $order = Order::create([
                'status' => OrderStatus::Pending,
                'subtotal' => $amount,
                'tax_amount' => '0.00',
                'shipping_amount' => '0.00',
                'discount_amount' => '0.00',
                'total_amount' => $amount,
                'currency' => 'USD',
                'placed_at' => now(),
                'metadata' => [
                    'checkout_path' => 'local',
                    'gateway_provider' => $account->gateway_provider->value,
                    'merchant_account_id' => $account->uuid,
                    'gateway_transaction_id' => $paymentResult->transactionId,
                    'lead_uuid' => $lead->uuid,
                ],
            ]);

            foreach ($items as $item) {
                $name = $item->itemable?->name ?? 'Unknown item';
                $price = (float) ($item->unit_price_snapshot ?? 0);

                $order->items()->create([
                    'name' => $name,
                    'sku' => $item->itemable?->provider_product_sku ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => $price,
                    'line_total' => $price * $item->quantity,
                ]);
            }

            $lead->update(['status' => LeadStatus::Completed]);

            $cart->items()->delete();

            return $order;
        });

        return CheckoutResultData::from([
            'order_uuid' => $order->uuid,
            'checkout_path' => 'local',
        ]);
    }
}
