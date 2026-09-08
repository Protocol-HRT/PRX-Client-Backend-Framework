<?php

namespace Tests\Feature\Checkout;

use App\Actions\Checkout\SubmitPrescribeRxCheckoutAction;
use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Models\Commerce\CartItem;
use App\Models\Lead;
use App\Services\PrescribeRx\Exceptions\PrescribeRxException;
use App\Settings\BillingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a shopper is allowed to be told when checkout fails.
 *
 * `CheckoutController` relayed `$e->getMessage()` from any `RuntimeException`
 * as a 422, and `atlas-protocol-web`'s `checkoutClient.js` puts that straight
 * on the page. That is a rule about a PHP class rather than about who a
 * sentence was written for, and three different things went out through it:
 *
 *   * copy genuinely meant for a shopper — "Cart is empty." — which is right;
 *   * an operator diagnostic telling a customer to "map the catalog first"
 *     and naming our `provider_package_id` / `provider_product_sku` columns;
 *   * `PrescribeRxException`, which is also a `RuntimeException`, and whose
 *     message is the clinical provider's own error text: absolute filesystem
 *     paths, and on one endpoint the full SQL statement with a
 *     `patient_chart_id` inside it.
 *
 * The type is now the contract: only `ActionException` carries a message
 * written to be shown. These tests pin the two that must never reach a page.
 */
class CheckoutErrorLeakTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Cart, Lead} */
    private function cartAndLead(bool $withItem = true): array
    {
        app(BillingSettings::class)->fill(['checkout_path' => 'prx'])->save();

        $cart = Cart::factory()->create();
        $lead = Lead::factory()->create(['cart_ulid' => $cart->ulid]);

        if ($withItem) {
            CartItem::factory()->create([
                'cart_id' => $cart->id,
                'itemable_type' => Product::class,
                'itemable_id' => Product::factory()->create()->id,
                'unit_price_snapshot' => 49.99,
                'quantity' => 1,
            ]);
        }

        return [$cart, $lead];
    }

    public function test_the_providers_stack_never_reaches_the_storefront(): void
    {
        [$cart, $lead] = $this->cartAndLead();

        // Verbatim shape of a real body from the provider: SQL, a chart id and
        // an absolute path to their source tree.
        $upstream = "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'recorded_at' in 'order clause' "
            ."(select * from `patient_vitals` where `patient_chart_id` = '01a07ea4-282e-70d1-b350-6fc58731c738') "
            .'at /var/www/html/prx-demo/app/Http/Controllers/Api/V1/Me/PatientSelfServiceController.php:155';

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function ($mock) use ($upstream): void {
            $mock->shouldReceive('execute')->andThrow(new PrescribeRxException($upstream, httpStatus: 500));
        });

        $response = $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ]);

        // 502: the fault is upstream, and the shopper did nothing wrong.
        $response->assertStatus(502);

        $body = $response->getContent();

        foreach (['SQLSTATE', 'recorded_at', 'patient_vitals', 'prx-demo', 'PatientSelfServiceController', '01a07ea4'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "The provider's error body leaked '{$leak}' to a shopper.");
        }
    }

    public function test_an_upstream_422_names_the_field_without_relaying_the_providers_prose(): void
    {
        [$cart, $lead] = $this->cartAndLead();

        $this->mock(SubmitPrescribeRxCheckoutAction::class, function ($mock): void {
            $mock->shouldReceive('execute')->andThrow(new PrescribeRxException(
                'Whatever prose the provider felt like returning.',
                httpStatus: 422,
                errors: ['patient.date_of_birth' => ['The date of birth must be a valid date.']],
            ));
        });

        $response = $this->postJson('/api/v1/checkout', ['cart_ulid' => $cart->ulid, 'lead_uuid' => $lead->uuid])
            ->assertStatus(422);

        // The field-keyed array survives — the storefront points at inputs with
        // it — while the provider's own sentence does not. Read out of the
        // decoded body rather than by dotted path: the key contains a dot.
        $body = $response->json();

        $this->assertSame(
            ['The date of birth must be a valid date.'],
            $body['errors']['patient.date_of_birth']
        );
        $this->assertStringNotContainsString('Whatever prose', $response->getContent());
    }

    public function test_an_unmapped_catalog_does_not_tell_a_customer_to_map_the_catalog(): void
    {
        // A real deployment fault, and it used to be reported to the shopper in
        // our internal vocabulary. Nothing they did caused it and nothing they
        // can do fixes it, so it belongs in the log.
        [$cart, $lead] = $this->cartAndLead();

        $response = $this->postJson('/api/v1/checkout', [
            'cart_ulid' => $cart->ulid,
            'lead_uuid' => $lead->uuid,
        ]);

        // Assert the outcome, not only the absences. A test that checks only
        // that four strings are missing stays green if a future change breaks
        // the request earlier — a 500 from the eager load, a 403 from a factory
        // change — and never reaches the branch it claims to cover.
        $response
            ->assertStatus(503)
            ->assertJson(['message' => 'We cannot take this order right now. Please contact support.']);

        $body = $response->getContent();

        foreach (['provider_package_id', 'provider_product_sku', 'Map the catalog', 'Prescribe-Rx selections'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "Checkout leaked the operator diagnostic '{$leak}' to a shopper.");
        }
    }
}
