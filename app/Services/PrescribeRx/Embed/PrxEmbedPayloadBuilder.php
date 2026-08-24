<?php

namespace App\Services\PrescribeRx\Embed;

use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Catalog\Product;
use App\Models\Lead;
use App\Settings\IntegrationSettings;

/**
 * Builds the JSON payload our Blade component hands to the prescribe-rx
 * embed SDK. Two responsibilities:
 *
 *   1) Translate a Lead's locally-collected fields into the snake_case keys
 *      the embed's prefill() method expects (so the user skips name / email /
 *      phone / DOB / address steps inside the embed).
 *   2) Translate the Lead's cart_items snapshot into the selectPackages /
 *      selectProducts / selectPlan calls that PRX uses to drive its dynamic
 *      step + question rendering. PRX's catalog is the source of truth for
 *      which questions appear; we just nominate the products.
 *
 * No PHI is read or stored here — this is purely demographic + cart data.
 */
class PrxEmbedPayloadBuilder
{
    public function __construct(
        protected IntegrationSettings $settings,
    ) {}

    /**
     * Build the full payload object for a Lead.
     *
     * @return array{
     *   embedCode: ?string,
     *   prefill: array<string, mixed>,
     *   packages: array<int, string>,
     *   products: array<int, string>,
     *   planIds: array<int, string>,
     *   skipSteps: array<int, string>,
     *   metadata: array<string, mixed>,
     * }
     */
    public function forLead(Lead $lead): array
    {
        return [
            'embedCode' => $this->settings->prescribe_rx_embed_code,
            'prefill' => $this->buildPrefill($lead),
            'packages' => $this->packageNumbersFromCart($lead),
            'products' => $this->productNumbersFromCart($lead),
            'planIds' => $this->planIdsFromCart($lead),
            // Step slugs are encounter-type-specific. The list comes from
            // config/prescribe-rx.php → embed.skip_steps and contains the
            // common variants for personal-info + product-selection. The
            // SDK silently ignores unknown slugs, so listing multiple
            // variants is safe — the embed picks the ones that match its
            // current schema. Adjust the config when you find the exact
            // slugs in PRX admin.
            'skipSteps' => $this->skipStepsForLead($lead),
            'metadata' => [
                'lead_uuid' => $lead->uuid,
                'lead_email' => $lead->email,
                'cart_subtotal' => $lead->cart_subtotal !== null ? (float) $lead->cart_subtotal : null,
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPrefill(Lead $lead): array
    {
        return array_filter([
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'date_of_birth' => $lead->date_of_birth?->toDateString(),
            'gender' => $lead->gender,
            'address_line1' => $lead->address_line1,
            'address_line2' => $lead->address_line2,
            'city' => $lead->city,
            'state' => $lead->state,
            'postal_code' => $lead->postal_code,
            'country' => $lead->country,
            // PRX also accepts a nested `address` object — emit both shapes
            // since different schema versions accept different keys. The
            // embed will use whichever its current encounter type expects.
            'address' => array_filter([
                'street' => $lead->address_line1,
                'street2' => $lead->address_line2,
                'city' => $lead->city,
                'state' => $lead->state,
                'zip' => $lead->postal_code,
                'country' => $lead->country,
            ], fn ($v) => $v !== null && $v !== ''),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Resolve package numbers (PKG-XXXXX) for every cart line of type=package.
     * Falls back to UUIDs if no number is mapped.
     *
     * @return array<int, string>
     */
    protected function packageNumbersFromCart(Lead $lead): array
    {
        $items = collect($lead->cart_items ?? [])->where('resource_type', 'package');
        if ($items->isEmpty()) {
            return [];
        }

        $packages = Package::query()->whereIn('id', $items->pluck('resource_id'))->get();

        return $packages
            ->map(fn (Package $p) => $p->prescribe_rx_package_number ?: $p->prescribe_rx_package_id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve product numbers (PROD-XXXXX) for every cart line of type=product.
     * Plan items also implicitly drag in their parent package's products via
     * the embed (PRX dereferences plan→package→items server-side); we don't
     * unroll that here.
     *
     * @return array<int, string>
     */
    protected function productNumbersFromCart(Lead $lead): array
    {
        $items = collect($lead->cart_items ?? [])->where('resource_type', 'product');
        if ($items->isEmpty()) {
            return [];
        }

        $products = Product::query()->whereIn('id', $items->pluck('resource_id'))->get();

        return $products
            ->map(fn (Product $p) => $p->prescribe_rx_product_number ?: $p->prescribe_rx_product_id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Step slugs to skip in the embed. Pulled from config so admin can
     * tune them per-encounter-type without redeploying. Could later be
     * conditioned on lead state (e.g. don't skip personal-info if a
     * required field is missing) — for now it's a static list.
     *
     * @return array<int, string>
     */
    protected function skipStepsForLead(Lead $lead): array
    {
        return array_values((array) config('prescribe-rx.embed.skip_steps', []));
    }

    /**
     * Resolve plan IDs (UUIDs) for every cart line of type=plan.
     *
     * @return array<int, string>
     */
    protected function planIdsFromCart(Lead $lead): array
    {
        $items = collect($lead->cart_items ?? [])->where('resource_type', 'plan');
        if ($items->isEmpty()) {
            return [];
        }

        $plans = Plan::query()->whereIn('id', $items->pluck('resource_id'))->get();

        return $plans
            ->map(fn (Plan $p) => $p->prescribe_rx_plan_id ?: $p->prescribe_rx_plan_number)
            ->filter()
            ->values()
            ->all();
    }
}
