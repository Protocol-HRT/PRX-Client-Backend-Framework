<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Actions\Checkout\SubmitPrescribeRxCheckoutAction;
use App\Data\Checkout\CheckoutData;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\V1\Checkout\CheckoutResource;
use App\Models\Commerce\Cart;
use App\Models\Lead;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * POST /api/v1/checkout
 *
 * Submits a cart + lead to the configured checkout provider and returns
 * the order UUID + a checkout path the frontend uses to load the next step
 * (e.g. loading the PRX embed with the returned encounter_id).
 *
 * Currently only the 'prx' path is implemented. The 'local' path returns
 * 503 until PaymentGatewayInterface and the local gateway are wired up.
 */
class CheckoutController extends ApiController
{
    public function __construct(
        private readonly SubmitPrescribeRxCheckoutAction $prescribeRx,
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    /**
     * Get the active payment gateway configuration for the checkout frontend.
     *
     * Returns the gateway provider name, the public/publishable key required to
     * initialize the gateway's client-side tokenization SDK (Accept.js, Collect.js,
     * Stripe.js, Square Web Payments), and the environment so the frontend can
     * target the correct SDK URL and form endpoint.
     *
     * @tags Checkout
     *
     * @unauthenticated
     */
    public function gatewayConfig(): JsonResponse
    {
        try {
            $account = $this->gatewayManager->defaultAccount();
        } catch (Throwable) {
            return $this->error('No active payment gateway is configured.', 503);
        }

        $data = [
            'gateway_provider' => $account->gateway_provider->value,
            'environment' => $account->environment->value,
            'public_key' => $account->getPublicKey(),
        ];

        // Square Web Payments SDK also requires the location ID for payment forms.
        if ($account->square_location_id) {
            $data['location_id'] = $account->square_location_id;
        }

        return $this->success($data);
    }

    /**
     * Submit a cart to checkout.
     *
     * Submits the identified cart and lead to the configured checkout provider (prescribe-rx
     * or local gateway). Returns the order UUID and a checkout path for the frontend to load
     * the next step. Requires matching cart_ulid and lead_uuid from the same session.
     *
     * @tags Checkout
     *
     * @unauthenticated
     */
    public function store(Request $request): JsonResponse
    {
        $data = CheckoutData::validateAndCreate($request->all());

        $cart = Cart::where('ulid', $data->cart_ulid)->firstOrFail();
        $lead = Lead::where('uuid', $data->lead_uuid)->firstOrFail();

        // Verify the lead was created in the same cart session.
        // cart_ulid is captured from X-Cart-Token when a lead is created,
        // so a caller who only knows one of the two identifiers cannot pair them.
        if (filled($lead->cart_ulid) && ! hash_equals($lead->cart_ulid, $cart->ulid)) {
            abort(403, 'Cart and lead do not belong to the same session.');
        }

        try {
            $result = $this->prescribeRx->execute($cart, $lead, $data->intake_answers);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Log::error('Checkout failed', [
                'cart_ulid' => $data->cart_ulid,
                'lead_uuid' => $data->lead_uuid,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Checkout could not be completed. Please try again.', 503);
        }

        return (new CheckoutResource($result))
            ->response()
            ->setStatusCode(201);
    }
}
