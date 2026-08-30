<?php

namespace Tests\Feature\Api\V1\Cart;

use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Models\Commerce\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ── GET /api/v1/cart ─────────────────────────────────────────────────

    public function test_get_cart_with_no_token_creates_new_cart_and_returns_token(): void
    {
        $response = $this->getJson('/api/v1/cart');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'email', 'item_count', 'subtotal', 'items', 'expires_at']]);

        $token = $response->json('data.token');
        $this->assertNotNull($token);
        $this->assertDatabaseHas('carts', ['ulid' => $token]);
    }

    public function test_get_cart_with_valid_token_returns_existing_cart(): void
    {
        $cart = Cart::factory()->create();

        $response = $this->getJson('/api/v1/cart', ['X-Cart-Token' => $cart->ulid]);

        $response->assertOk()
            ->assertJsonPath('data.token', $cart->ulid);

        $this->assertDatabaseCount('carts', 1);
    }

    public function test_get_cart_with_expired_token_creates_new_cart(): void
    {
        $expired = Cart::factory()->expired()->create();

        $response = $this->getJson('/api/v1/cart', ['X-Cart-Token' => $expired->ulid]);

        $response->assertOk();

        $newToken = $response->json('data.token');
        $this->assertNotSame($expired->ulid, $newToken);
        $this->assertDatabaseCount('carts', 2);
    }

    public function test_cart_response_includes_correct_shape(): void
    {
        $product = Product::factory()->create(['retail_price' => 99.99]);
        $cart = Cart::factory()->create();
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 2,
            'unit_price_snapshot' => 99.99,
        ]);

        $response = $this->getJson('/api/v1/cart', ['X-Cart-Token' => $cart->ulid]);

        $response->assertOk()
            ->assertJsonPath('data.token', $cart->ulid)
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.subtotal', 199.98);
    }

    // ── POST /api/v1/cart/items ──────────────────────────────────────────

    public function test_add_product_to_cart(): void
    {
        $product = Product::factory()->create(['retail_price' => 149.00]);
        $cart = Cart::factory()->create();

        $response = $this->postJson('/api/v1/cart/items', [
            'type' => 'product',
            'id' => $product->id,
            'quantity' => 1,
        ], ['X-Cart-Token' => $cart->ulid]);

        $response->assertStatus(201)
            ->assertJsonPath('data.token', $cart->ulid)
            ->assertJsonPath('data.item_count', 1);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_add_package_with_plan_to_cart(): void
    {
        $package = Package::factory()->create(['retail_price' => 299.00]);
        $plan = Plan::factory()->create(['package_id' => $package->id, 'retail_price' => 199.00]);
        $cart = Cart::factory()->create();

        $response = $this->postJson('/api/v1/cart/items', [
            'type' => 'package',
            'id' => $package->id,
            'plan_id' => $plan->id,
            'quantity' => 1,
        ], ['X-Cart-Token' => $cart->ulid]);

        $response->assertStatus(201)
            ->assertJsonPath('data.item_count', 1);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'itemable_type' => Package::class,
            'itemable_id' => $package->id,
            'plan_id' => $plan->id,
        ]);
    }

    public function test_add_product_with_term_plan_snapshots_plan_price(): void
    {
        $product = Product::factory()->create(['retail_price' => 299.00]);
        $plan = Plan::factory()->create(['product_id' => $product->id, 'retail_price' => 1435.20]);
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', [
            'type' => 'product',
            'id' => $product->id,
            'plan_id' => $plan->id,
        ], ['X-Cart-Token' => $cart->ulid])->assertStatus(201);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'plan_id' => $plan->id,
            'unit_price_snapshot' => 1435.20,
        ]);
    }

    public function test_a_package_can_be_bought_on_its_own_without_a_plan(): void
    {
        // `plan_id` used to be `requiredIf(type === 'package')`, so a stack was
        // purchasable ONLY as a subscription — there was no way to buy the thing
        // itself. A package is a product, or a group of them, with its own
        // price; plans are the separate recurring offer alongside it.
        //
        // Note what is asserted: the snapshot is the PACKAGE's own price, not a
        // plan's. That branch existed all along and was simply unreachable.
        $package = Package::factory()->create(['retail_price' => 399.00, 'sale_price' => null]);
        Plan::factory()->create(['package_id' => $package->id, 'retail_price' => 279.99]);
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', [
            'type' => 'package',
            'id' => $package->id,
            'quantity' => 2,
        ], ['X-Cart-Token' => $cart->ulid])->assertCreated();

        $item = CartItem::query()->where('itemable_id', $package->id)->firstOrFail();

        $this->assertNull($item->plan_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('399.00', (string) $item->unit_price_snapshot);
    }

    public function test_a_package_sale_price_is_what_gets_snapshotted(): void
    {
        $package = Package::factory()->create(['retail_price' => 399.00, 'sale_price' => 349.00]);
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', [
            'type' => 'package',
            'id' => $package->id,
        ], ['X-Cart-Token' => $cart->ulid])->assertCreated();

        $this->assertSame(
            '349.00',
            (string) CartItem::query()->where('itemable_id', $package->id)->firstOrFail()->unit_price_snapshot,
        );
    }

    public function test_a_package_bought_once_and_the_same_package_on_a_plan_are_separate_lines(): void
    {
        // The dedupe key already included `plan_id`, so this needed no change —
        // but it is the case that would silently merge a subscription into a
        // one-off purchase, which is worth a test rather than a reading of the
        // query.
        $package = Package::factory()->create(['retail_price' => 399.00]);
        $plan = Plan::factory()->create(['package_id' => $package->id, 'retail_price' => 279.99]);
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', ['type' => 'package', 'id' => $package->id], ['X-Cart-Token' => $cart->ulid])->assertCreated();
        $this->postJson('/api/v1/cart/items', ['type' => 'package', 'id' => $package->id, 'plan_id' => $plan->id], ['X-Cart-Token' => $cart->ulid])->assertCreated();

        $this->assertSame(2, CartItem::query()->where('cart_id', $cart->id)->count());
    }

    public function test_add_item_rejects_plan_belonging_to_another_item(): void
    {
        $product = Product::factory()->create(['retail_price' => 299.00]);
        $otherProduct = Product::factory()->create();
        $foreignPlan = Plan::factory()->create(['product_id' => $otherProduct->id]);
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', [
            'type' => 'product',
            'id' => $product->id,
            'plan_id' => $foreignPlan->id,
        ], ['X-Cart-Token' => $cart->ulid])->assertStatus(422);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_add_same_product_twice_increments_quantity(): void
    {
        $product = Product::factory()->create(['retail_price' => 99.00]);
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', [
            'type' => 'product',
            'id' => $product->id,
            'quantity' => 1,
        ], ['X-Cart-Token' => $cart->ulid]);

        $this->postJson('/api/v1/cart/items', [
            'type' => 'product',
            'id' => $product->id,
            'quantity' => 2,
        ], ['X-Cart-Token' => $cart->ulid]);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'itemable_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_add_item_validates_required_fields(): void
    {
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', [], ['X-Cart-Token' => $cart->ulid])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'id']);
    }

    public function test_add_item_uses_sale_price_when_available(): void
    {
        $product = Product::factory()->create([
            'retail_price' => 150.00,
            'sale_price' => 99.00,
        ]);
        $cart = Cart::factory()->create();

        $this->postJson('/api/v1/cart/items', [
            'type' => 'product',
            'id' => $product->id,
            'quantity' => 1,
        ], ['X-Cart-Token' => $cart->ulid]);

        $this->assertDatabaseHas('cart_items', [
            'itemable_id' => $product->id,
            'unit_price_snapshot' => '99.00',
        ]);
    }

    // ── PATCH /api/v1/cart/items/{cartItem} ──────────────────────────────

    public function test_update_item_quantity(): void
    {
        $product = Product::factory()->create(['retail_price' => 100.00]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price_snapshot' => 100.00,
        ]);

        $this->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 3], ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonPath('data.item_count', 3);

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 3]);
    }

    public function test_update_item_with_quantity_zero_removes_item(): void
    {
        $product = Product::factory()->create(['retail_price' => 100.00]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 2,
            'unit_price_snapshot' => 100.00,
        ]);

        $this->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 0], ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    // ── DELETE /api/v1/cart/items/{cartItem} ─────────────────────────────

    public function test_remove_item_from_cart(): void
    {
        $product = Product::factory()->create(['retail_price' => 100.00]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price_snapshot' => 100.00,
        ]);

        $this->deleteJson("/api/v1/cart/items/{$item->id}", [], ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonPath('data.item_count', 0);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    // ── DELETE /api/v1/cart ───────────────────────────────────────────────

    public function test_clear_cart_removes_all_items(): void
    {
        $product = Product::factory()->create(['retail_price' => 100.00]);
        $cart = Cart::factory()->create();

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price_snapshot' => 100.00,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 2,
            'unit_price_snapshot' => 100.00,
        ]);

        $response = $this->deleteJson('/api/v1/cart', [], ['X-Cart-Token' => $cart->ulid]);

        $response->assertOk()
            ->assertJsonPath('data.item_count', 0);

        $this->assertEquals(0, $response->json('data.subtotal'));
        $this->assertDatabaseCount('cart_items', 0);
    }

    // ── Response shape ───────────────────────────────────────────────────

    public function test_cart_response_items_include_item_and_plan_data(): void
    {
        $package = Package::factory()->create(['retail_price' => 299.00]);
        $plan = Plan::factory()->create(['package_id' => $package->id, 'retail_price' => 199.00]);
        $cart = Cart::factory()->create();

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'itemable_type' => Package::class,
            'itemable_id' => $package->id,
            'plan_id' => $plan->id,
            'quantity' => 1,
            'unit_price_snapshot' => 199.00,
        ]);

        $response = $this->getJson('/api/v1/cart', ['X-Cart-Token' => $cart->ulid]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'item_count',
                    'subtotal',
                    'items' => [
                        [
                            'id',
                            'type',
                            'quantity',
                            'unit_price',
                            'line_total',
                            'item',
                            'plan',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.items.0.type', 'Package')
            ->assertJsonPath('data.items.0.plan.id', $plan->id);
    }
}
