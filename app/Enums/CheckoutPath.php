<?php

namespace App\Enums;

enum CheckoutPath: string
{
    case Local = 'local';      // NMI / Authorize.net merchant of record on this site.
    case PrescribeRx = 'prx';  // Hand-off to prescribe-rx embed for payment.

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local checkout',
            self::PrescribeRx => 'PRX embed',
        };
    }
}
