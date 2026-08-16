<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BillingSettings extends Settings
{
    /**
     * Which checkout provider handles order submission.
     *
     * 'prx'   — All orders go through Prescribe-Rx (embed handoff).
     *           No local payment processing; PRX collects payment inside its embed.
     * 'local' — Orders are charged locally through the configured merchant account
     *           (NMI / Authorize.Net / Stripe / Square). PRX is not involved in payment.
     */
    public string $checkout_path = 'prx';

    /**
     * Show upsell / cross-sell suggestions (driven by catalog relations)
     * in the cart drawer and on the checkout page.
     */
    public bool $upsells_enabled = true;

    /** Maximum number of upsell suggestions returned per request. */
    public int $upsells_limit = 4;

    public static function group(): string
    {
        return 'billing';
    }
}
