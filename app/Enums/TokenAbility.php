<?php

namespace App\Enums;

enum TokenAbility: string
{
    case PublicRead = 'public:read';
    case Checkout = 'checkout:*';
    case PatientPortal = 'patient:*';
    case AdminApi = 'admin:*';

    public function label(): string
    {
        return match ($this) {
            self::PublicRead => 'Public read (catalog, CMS, blog)',
            self::Checkout => 'Checkout (leads, cart, order submission)',
            self::PatientPortal => 'Patient portal (authenticated patient routes)',
            self::AdminApi => 'Admin API (server-to-server)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
