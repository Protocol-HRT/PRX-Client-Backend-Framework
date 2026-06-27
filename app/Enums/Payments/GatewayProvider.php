<?php

namespace App\Enums\Payments;

enum GatewayProvider: string
{
    case Nmi = 'nmi';
    case AuthorizeNet = 'authorize_net';

    public function label(): string
    {
        return match ($this) {
            self::Nmi => 'NMI',
            self::AuthorizeNet => 'Authorize.Net',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Nmi => 'primary',
            self::AuthorizeNet => 'success',
        };
    }
}
