<?php

namespace Tests\Feature\Api\V1\Checkout;

use App\Actions\Checkout\SubmitPrescribeRxCheckoutAction;
use App\Data\Checkout\CheckoutResultData;
use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Models\Commerce\CartItem;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a cart + lead pair that share the same cart_ulid (normal checkout flow).
     *
     * @return array{Cart, Lead}
     */
    private function makeLinkedCartAndLead(): array
    {
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);

        return [$cart, $lead];
    }

    public function test_submits_cart_successfully_and_returns_201(): void
    {
        [$cart, $lead] = $this->makeLinkedCartAndLead();

        $product = Product::factory()->create(['provider_product_id' => 'prx-prod-123']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'unit_price_snapshot' => 99.99,
        ]);

        $result = CheckoutResultData::from([
            'order_uuid' => 'order-uuid-123',
            'checkout_path' => 'prx',
            'prescribe_rx' => [
                'encounter_id' => 'enc-abc',
                'encounter_number' => 'ENC-001',
                'patient_id' => 'pt-xyz',
                'status' => 'pending_intake',
            ],
        ]);

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function (MockInterface $mock) use ($result): void {
            $mock->shouldReceive('execute')->once()->andReturn($result);
        });

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ])->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'order_uuid',
                    'checkout_path',
                    'prescribe_rx' => ['encounter_id', 'encounter_number', 'patient_id', 'status'],
                ],
            ])
            ->assertJson([
                'data' => [
                    'order_uuid' => 'order-uuid-123',
                    'checkout_path' => 'prx',
                ],
            ]);
    }

    public function test_rejects_mismatched_cart_and_lead(): void
    {
        $cart = Cart::factory()->create();
        $otherCart = Cart::factory()->create();
        // Lead was created in a different cart session
        $lead = Lead::factory()->create(['cart_ulid' => $otherCart->ulid]);

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ])->assertForbidden();
    }

    public function test_allows_checkout_when_lead_has_no_cart_ulid(): void
    {
        // Leads created before cart_ulid was tracked have null cart_ulid — allow through.
        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => null]);

        $result = CheckoutResultData::from(['order_uuid' => 'order-uuid-789', 'checkout_path' => 'prx']);

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function (MockInterface $mock) use ($result): void {
            $mock->shouldReceive('execute')->once()->andReturn($result);
        });

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ])->assertCreated();
    }

    public function test_returns_404_when_cart_not_found(): void
    {
        $lead = Lead::factory()->create();

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => 'nonexistent-ulid',
            'lead_uuid' => $lead->uuid,
        ])->assertNotFound();
    }

    public function test_returns_404_when_lead_not_found(): void
    {
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => 'nonexistent-uuid',
        ])->assertNotFound();
    }

    public function test_returns_422_for_missing_required_fields(): void
    {
        $this->postJson('/api/v1/checkout', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart_ulid', 'lead_uuid']);
    }

    public function test_returns_422_when_action_throws_runtime_exception(): void
    {
        [$cart, $lead] = $this->makeLinkedCartAndLead();

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Cart is empty.'));
        });

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ])->assertUnprocessable()
            ->assertJson(['message' => 'Cart is empty.']);
    }

    public function test_returns_503_when_action_throws_unexpected_exception(): void
    {
        [$cart, $lead] = $this->makeLinkedCartAndLead();

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new \Exception('Connection refused'));
        });

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ])->assertServiceUnavailable()
            ->assertJson(['message' => 'Checkout could not be completed. Please try again.']);
    }

    public function test_passes_intake_answers_to_action(): void
    {
        [$cart, $lead] = $this->makeLinkedCartAndLead();

        $result = CheckoutResultData::from(['order_uuid' => 'order-uuid-456', 'checkout_path' => 'prx']);

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function (MockInterface $mock) use ($result): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(function ($cartArg, $leadArg, array $answers): bool {
                    return $answers['weight_lbs'] === 180 && $answers['blood_pressure'] === '120/80';
                })
                ->andReturn($result);
        });

        $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
            'intake_answers' => ['weight_lbs' => 180, 'blood_pressure' => '120/80'],
        ])->assertCreated();
    }
}
