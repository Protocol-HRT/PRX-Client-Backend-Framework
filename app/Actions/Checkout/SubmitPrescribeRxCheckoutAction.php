<?php

namespace App\Actions\Checkout;

use App\Data\Checkout\CheckoutResultData;
use App\Data\PrescribeRx\AddressData;
use App\Data\PrescribeRx\PatientData;
use App\Data\PrescribeRx\UnifiedIntakeRequestData;
use App\Enums\EncounterStatus;
use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Commerce\Cart;
use App\Models\Commerce\CartItem;
use App\Models\Commerce\Encounter;
use App\Models\Commerce\Order;
use App\Models\Lead;
use App\Services\PrescribeRx\Client;
use App\Services\PrescribeRx\Exceptions\PrescribeRxException;
use App\Settings\IntegrationSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubmitPrescribeRxCheckoutAction
{
    public function __construct(
        private readonly Client $prx,
        private readonly IntegrationSettings $settings,
    ) {}

    /**
     * Submit the cart to Prescribe-Rx as a unified intake, then
     * create the local Encounter + Order shell and mark the lead handed off.
     *
     * @param  array<string, mixed>  $intakeAnswers
     *
     * @throws RuntimeException|PrescribeRxException
     */
    public function execute(Cart $cart, Lead $lead, array $intakeAnswers = []): CheckoutResultData
    {
        $items = $cart->items()->with(['itemable', 'itemable.products', 'plan'])->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Cart is empty.');
        }

        $productIds = $this->resolveProductIds($items);

        if (empty($productIds)) {
            throw new RuntimeException('No Prescribe-Rx product IDs found on cart items. Ensure catalog items have provider_product_id / provider_product_ids configured.');
        }

        $request = UnifiedIntakeRequestData::from([
            'patient' => $this->buildPatient($lead),
            'encounter_type_id' => $this->settings->prescribe_rx_encounter_type_id,
            'sales_org_id' => $this->settings->prescribe_rx_sales_org_id,
            'client_id' => $this->settings->prescribe_rx_client_id,
            'product_ids' => $productIds,
            'answers' => $intakeAnswers,
        ]);

        // PRX API call is intentionally outside the DB transaction.
        $prxResponse = $this->prx->submitUnifiedIntake($request);

        $isSandbox = $this->settings->prescribe_rx_environment === 'sandbox';

        $order = DB::transaction(function () use ($cart, $lead, $items, $prxResponse, $isSandbox): Order {
            $encounter = Encounter::create([
                'lead_id' => $lead->id,
                'prescribe_rx_encounter_id' => $prxResponse->encounter_id,
                'prescribe_rx_patient_id' => $prxResponse->patient_chart_id,
                'prescribe_rx_encounter_type_id' => $this->settings->prescribe_rx_encounter_type_id,
                'status' => EncounterStatus::Submitted,
                'submitted_at' => now(),
                'is_sandbox' => $isSandbox,
                'total_amount' => $cart->subtotal(),
            ]);

            $order = Order::create([
                'encounter_id' => $encounter->id,
                'status' => OrderStatus::Pending,
                'subtotal' => $cart->subtotal(),
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $cart->subtotal(),
                'currency' => 'USD',
                'placed_at' => now(),
            ]);

            // Snapshot order items from cart
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

            $lead->update([
                'status' => LeadStatus::HandedOff->value,
                'prescribe_rx_encounter_id' => $prxResponse->encounter_id,
                'prescribe_rx_patient_id' => $prxResponse->patient_chart_id,
                'prescribe_rx_response' => $prxResponse->toArray(),
                'handed_off_at' => now(),
            ]);

            // Clear cart items but keep the cart record for analytics
            $cart->items()->delete();

            return $order;
        });

        return CheckoutResultData::from([
            'order_uuid' => $order->uuid,
            'checkout_path' => 'prx',
            'prescribe_rx' => [
                'encounter_id' => $prxResponse->encounter_id,
                'encounter_number' => $prxResponse->encounter_number,
                'patient_id' => $prxResponse->patient_chart_id,
                'status' => $prxResponse->status,
            ],
        ]);
    }

    /**
     * Resolve PRX product IDs from the cart items.
     * Priority: Plan.provider_product_ids > Product.provider_product_id > Package products.
     *
     * @param  Collection<int, CartItem>  $items
     * @return list<string>
     */
    private function resolveProductIds(Collection $items): array
    {
        $productIds = [];

        foreach ($items as $item) {
            $itemable = $item->itemable;
            if (! $itemable) {
                continue;
            }

            if ($item->plan_id) {
                $plan = $item->plan;
                if ($plan?->provider_product_ids) {
                    array_push($productIds, ...$plan->provider_product_ids);
                }
            } elseif ($itemable instanceof Plan) {
                if ($itemable->provider_product_ids) {
                    array_push($productIds, ...$itemable->provider_product_ids);
                }
            } elseif ($itemable instanceof Product) {
                if ($itemable->provider_product_id) {
                    $productIds[] = $itemable->provider_product_id;
                }
            } elseif ($itemable instanceof Package) {
                foreach ($itemable->products as $product) {
                    if ($product->provider_product_id) {
                        $productIds[] = $product->provider_product_id;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($productIds)));
    }

    private function buildPatient(Lead $lead): PatientData
    {
        $address = null;

        if ($lead->address_line1 && $lead->city && $lead->state && $lead->postal_code) {
            $address = AddressData::from([
                'street' => trim($lead->address_line1.' '.($lead->address_line2 ?? '')),
                'city' => $lead->city,
                'state' => $lead->state,
                'zip' => $lead->postal_code,
                'country' => $lead->country ?? 'US',
            ]);
        }

        return PatientData::from([
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'date_of_birth' => $lead->date_of_birth?->toDateString(),
            'phone' => $lead->phone,
            'gender' => $lead->gender,
            'address' => $address,
        ]);
    }
}
