<?php

namespace App\Enums\Payments;

enum GatewayEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Sandbox => 'Sandbox',
            self::Production => 'Production',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sandbox => 'info',
            self::Production => 'success',
        };
    }
}
