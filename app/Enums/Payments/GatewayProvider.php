<?php

namespace App\Enums\Payments;

enum GatewayProvider: string
{
    case Nmi = 'nmi';
    case AuthorizeNet = 'authorize_net';
    case Stripe = 'stripe';
    case Square = 'square';

    public function label(): string
    {
        return match ($this) {
            self::Nmi => 'NMI',
            self::AuthorizeNet => 'Authorize.Net',
            self::Stripe => 'Stripe',
            self::Square => 'Square',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Nmi => 'warning',
            self::AuthorizeNet => 'success',
            self::Stripe => 'primary',
            self::Square => 'info',
        };
    }
}
