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
