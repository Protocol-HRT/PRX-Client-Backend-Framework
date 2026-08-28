<?php

namespace Tests\Feature\Checkout;

use App\Actions\Checkout\SubmitLocalCheckoutAction;
use App\Actions\Checkout\SubmitPrescribeRxCheckoutAction;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Checkout\CheckoutResultData;
use App\Data\Payments\PaymentResult;
use App\Enums\LeadStatus;
use App\Enums\Payments\GatewayProvider;
use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Models\Commerce\CartItem;
use App\Models\Commerce\Order;
use App\Models\Lead;
use App\Models\Payments\MerchantAccount;
use App\Services\Payments\PaymentGatewayManager;
use App\Settings\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class LocalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setLocalPath(): void
    {
        app(BillingSettings::class)->fill(['checkout_path' => 'local'])->save();
    }

    private function makeLinkedCartAndLead(): array
    {
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);

        return [$cart, $lead];
    }

    private function addProductToCart(Cart $cart, float $price = 49.99): CartItem
    {
        $product = Product::factory()->create();

        return CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'unit_price_snapshot' => $price,
            'quantity' => 1,
        ]);
    }

    private function makeActiveNmiAccount(): MerchantAccount
    {
        return MerchantAccount::factory()->nmi()->create([
            'is_active' => true,
            'is_default' => true,
            'nmi_security_key' => 'test-key',
        ]);
    }

    // ── Controller routing ────────────────────────────────────────────────

    public function test_local_path_requires_payment_method_field(): void
    {
        $this->setLocalPath();
        [$cart, $lead] = $this->makeLinkedCartAndLead();

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ])->assertUnprocessable()
            ->assertJsonFragment(['message' => 'payment_method is required for local checkout.']);
    }

    public function test_local_path_returns_201_on_success(): void
    {
        $this->setLocalPath();
        [$cart, $lead] = $this->makeLinkedCartAndLead();
        $this->addProductToCart($cart);

        $this->mock(SubmitLocalCheckoutAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(CheckoutResultData::from([
                    'order_uuid' => 'local-order-uuid',
                    'checkout_path' => 'local',
                ]));
        });

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
            'payment_method' => ['payment_token' => 'tok_test'],
        ])->assertCreated()
            ->assertJsonPath('data.checkout_path', 'local')
            ->assertJsonPath('data.order_uuid', 'local-order-uuid');
    }

    public function test_prx_path_ignores_payment_method(): void
    {
        // Default path is 'prx' — payment_method should be silently ignored
        [$cart, $lead] = $this->makeLinkedCartAndLead();

        $result = CheckoutResultData::from(['order_uuid' => 'prx-order', 'checkout_path' => 'prx']);

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function (MockInterface $mock) use ($result): void {
            $mock->shouldReceive('execute')->once()->andReturn($result);
        });

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
            'payment_method' => ['payment_token' => 'tok_test'],
        ])->assertCreated()
            ->assertJsonPath('data.checkout_path', 'prx');
    }

    // ── SubmitLocalCheckoutAction unit tests ─────────────────────────────

    public function test_action_throws_when_cart_is_empty(): void
    {
        $account = $this->makeActiveNmiAccount();
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cart is empty.');

        app(SubmitLocalCheckoutAction::class)->execute($cart, $lead, ['payment_token' => 'tok_test']);
    }

    /**
     * Wire a mock PaymentGatewayInterface through the manager.
     * Gateway classes are final so we mock the interface, not the concrete.
     */
    private function mockGateway(callable $configure): void
    {
        $gateway = $this->mock(PaymentGatewayInterface::class, $configure);

        $this->mock(PaymentGatewayManager::class, function (MockInterface $mock) use ($gateway): void {
            $mock->shouldReceive('defaultAccount')->andReturn($this->makeActiveNmiAccount());
            $mock->shouldReceive('forAccount')->andReturn($gateway);
        });
    }

    public function test_action_throws_when_gateway_declines(): void
    {
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);
        $this->addProductToCart($cart, 99.00);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('sale')->once()->andReturn(
                PaymentResult::failure('Card declined.', GatewayProvider::Nmi)
            );
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Card declined.');

        app(SubmitLocalCheckoutAction::class)->execute($cart, $lead, ['payment_token' => 'tok_bad']);
    }

    public function test_action_creates_order_and_items_on_success(): void
    {
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);
        $this->addProductToCart($cart, 49.99);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('sale')->once()->andReturn(PaymentResult::from([
                'success' => true,
                'status' => 'succeeded',
                'transactionId' => 'txn-abc123',
                'message' => 'SUCCESS',
                'amount' => '49.99',
                'currency' => 'USD',
                'gatewayProvider' => GatewayProvider::Nmi,
            ]));
        });

        $result = app(SubmitLocalCheckoutAction::class)->execute($cart, $lead, ['payment_token' => 'tok_ok']);

        $this->assertEquals('local', $result->checkout_path);
        $this->assertNotEmpty($result->order_uuid);

        $order = Order::where('uuid', $result->order_uuid)->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->items);
        $this->assertEquals('txn-abc123', $order->metadata['gateway_transaction_id']);
    }

    public function test_action_marks_lead_completed_on_success(): void
    {
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);
        $this->addProductToCart($cart);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('sale')->once()->andReturn(PaymentResult::from([
                'success' => true,
                'status' => 'succeeded',
                'transactionId' => 'txn-ok',
                'message' => 'SUCCESS',
                'gatewayProvider' => GatewayProvider::Nmi,
            ]));
        });

        app(SubmitLocalCheckoutAction::class)->execute($cart, $lead, ['payment_token' => 'tok_ok']);

        $this->assertEquals(LeadStatus::Completed->value, $lead->fresh()->status);
    }

    public function test_action_clears_cart_items_on_success(): void
    {
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);
        $this->addProductToCart($cart);
        $this->addProductToCart($cart, 29.99);

        $this->mockGateway(function (MockInterface $mock): void {
            $mock->shouldReceive('sale')->once()->andReturn(PaymentResult::from([
                'success' => true,
                'status' => 'succeeded',
                'transactionId' => 'txn-clear',
                'message' => 'SUCCESS',
                'gatewayProvider' => GatewayProvider::Nmi,
            ]));
        });

        app(SubmitLocalCheckoutAction::class)->execute($cart, $lead, ['payment_token' => 'tok_ok']);

        $this->assertCount(0, $cart->fresh()->items);
    }
}
