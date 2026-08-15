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

    public static function group(): string
    {
        return 'billing';
    }
}
