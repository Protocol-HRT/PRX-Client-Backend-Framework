<?php

namespace Tests\Feature\Api\V1\Cart;

use App\Enums\CatalogRelationType;
use App\Models\Catalog\CatalogRelation;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Settings\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function cartWith(Product|Package $itemable): Cart
    {
        $cart = Cart::factory()->create();

        $cart->items()->create([
            'itemable_type' => $itemable::class,
            'itemable_id' => $itemable->id,
            'quantity' => 1,
            'unit_price_snapshot' => 100,
        ]);

        return $cart;
    }

    private function relate(Product|Package $source, Product|Package $related, CatalogRelationType $type): void
    {
        CatalogRelation::create([
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->id,
            'related_type' => $related->getMorphClass(),
            'related_id' => $related->id,
            'relation_type' => $type,
        ]);
    }

    public function test_empty_cart_returns_no_suggestions(): void
    {
        $this->getJson('/api/v1/cart/suggestions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pairs_with_items_are_suggested_for_cart_contents(): void
    {
        $inCart = Product::factory()->create();
        $companion = Product::factory()->create();
        $this->relate($inCart, $companion, CatalogRelationType::PairsWith);

        $cart = $this->cartWith($inCart);

        $this->getJson('/api/v1/cart/suggestions', ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $companion->slug)
            ->assertJsonPath('data.0.type', 'product');
    }

    public function test_items_already_in_cart_are_excluded(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $this->relate($first, $second, CatalogRelationType::PairsWith);

        $cart = $this->cartWith($first);
        $cart->items()->create([
            'itemable_type' => Product::class,
            'itemable_id' => $second->id,
            'quantity' => 1,
            'unit_price_snapshot' => 100,
        ]);

        $this->getJson('/api/v1/cart/suggestions', ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pairs_with_takes_priority_over_related_and_limit_is_respected(): void
    {
        $inCart = Product::factory()->create();
        $pairsWith = Product::factory()->create();
        $related = Product::factory()->create();
        $this->relate($inCart, $pairsWith, CatalogRelationType::PairsWith);
        $this->relate($inCart, $related, CatalogRelationType::Related);

        $settings = app(BillingSettings::class);
        $settings->upsells_limit = 1;
        $settings->save();

        $cart = $this->cartWith($inCart);

        $this->getJson('/api/v1/cart/suggestions', ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $pairsWith->slug);
    }

    public function test_package_suggestions_include_type_for_frontend_routing(): void
    {
        $inCart = Product::factory()->create();
        $stack = Package::factory()->create();
        $this->relate($inCart, $stack, CatalogRelationType::PairsWith);

        $cart = $this->cartWith($inCart);

        $this->getJson('/api/v1/cart/suggestions', ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonPath('data.0.type', 'package')
            ->assertJsonPath('data.0.slug', $stack->slug);
    }

    public function test_draft_related_items_are_not_suggested(): void
    {
        $inCart = Product::factory()->create();
        $draft = Product::factory()->draft()->create();
        $this->relate($inCart, $draft, CatalogRelationType::PairsWith);

        $cart = $this->cartWith($inCart);

        $this->getJson('/api/v1/cart/suggestions', ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_disabled_upsells_return_empty_list(): void
    {
        $inCart = Product::factory()->create();
        $companion = Product::factory()->create();
        $this->relate($inCart, $companion, CatalogRelationType::PairsWith);

        $settings = app(BillingSettings::class);
        $settings->upsells_enabled = false;
        $settings->save();

        $cart = $this->cartWith($inCart);

        $this->getJson('/api/v1/cart/suggestions', ['X-Cart-Token' => $cart->ulid])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
