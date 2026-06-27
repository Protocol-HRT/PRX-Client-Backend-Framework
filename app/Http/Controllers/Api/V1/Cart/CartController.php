<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Cart\CartResource;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Models\Commerce\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cart endpoints — token-identified via X-Cart-Token header.
 * No authentication required.
 *
 * GET    /api/v1/cart
 * POST   /api/v1/cart/items
 * PATCH  /api/v1/cart/items/{cartItem}
 * DELETE /api/v1/cart/items/{cartItem}
 * DELETE /api/v1/cart
 */
class CartController extends ApiController
{
    /**
     * Resolve an existing, non-expired cart from the X-Cart-Token header,
     * or create a fresh one if the token is absent or the cart has expired.
     */
    private function resolveCart(Request $request): Cart
    {
        $token = $request->header('X-Cart-Token');

        if ($token) {
            $cart = Cart::where('ulid', $token)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->first();

            if ($cart) {
                return $cart;
            }
        }

        return Cart::create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * GET /api/v1/cart
     *
     * Returns the current cart (creating one if needed).
     * Always includes the token so the frontend can persist it.
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cart->load(['items.itemable', 'items.plan']);

        return $this->success((new CartResource($cart))->toArray($request));
    }

    /**
     * POST /api/v1/cart/items
     *
     * Add a product or package+plan to the cart.
     * Increments quantity if the same item+plan combination already exists.
     */
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:product,package'],
            'id' => ['required', 'integer'],
            'plan_id' => ['nullable', 'integer', Rule::requiredIf($request->input('type') === 'package')],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $cart = $this->resolveCart($request);

        $itemableClass = $validated['type'] === 'product'
            ? Product::class
            : Package::class;

        $itemable = $itemableClass::findOrFail($validated['id']);

        // Resolve the display price from the plan (for packages) or the item itself.
        $price = null;
        if ($validated['type'] === 'package' && ! empty($validated['plan_id'])) {
            $plan = Plan::findOrFail($validated['plan_id']);
            $price = $plan->sale_price ?? $plan->retail_price;
        } else {
            $price = $itemable->sale_price ?? $itemable->retail_price;
        }

        // Increment quantity if the same item+plan combination is already in the cart.
        $existing = $cart->items()
            ->where('itemable_type', $itemableClass)
            ->where('itemable_id', $validated['id'])
            ->where('plan_id', $validated['plan_id'] ?? null)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $validated['quantity'] ?? 1);
        } else {
            $cart->items()->create([
                'itemable_type' => $itemableClass,
                'itemable_id' => $validated['id'],
                'plan_id' => $validated['plan_id'] ?? null,
                'quantity' => $validated['quantity'] ?? 1,
                'unit_price_snapshot' => $price,
            ]);
        }

        $cart->load(['items.itemable', 'items.plan']);

        return $this->success((new CartResource($cart))->toArray($request), status: 201);
    }

    /**
     * PATCH /api/v1/cart/items/{cartItem}
     *
     * Update quantity for a specific cart item.
     * Passing quantity=0 removes the item entirely.
     */
    public function updateItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        if ($validated['quantity'] === 0) {
            $cartItem->delete();
        } else {
            $cartItem->update(['quantity' => $validated['quantity']]);
        }

        $cart = Cart::findOrFail($cartItem->cart_id);
        $cart->load(['items.itemable', 'items.plan']);

        return $this->success((new CartResource($cart))->toArray($request));
    }

    /**
     * DELETE /api/v1/cart/items/{cartItem}
     *
     * Remove a single item from the cart.
     */
    public function removeItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $cartId = $cartItem->cart_id;
        $cartItem->delete();

        $cart = Cart::findOrFail($cartId);
        $cart->load(['items.itemable', 'items.plan']);

        return $this->success((new CartResource($cart))->toArray($request));
    }

    /**
     * DELETE /api/v1/cart
     *
     * Remove all items from the resolved cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $cart->items()->delete();
        $cart->load(['items.itemable', 'items.plan']);

        return $this->success((new CartResource($cart))->toArray($request));
    }
}
