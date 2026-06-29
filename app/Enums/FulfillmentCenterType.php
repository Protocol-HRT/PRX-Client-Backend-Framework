<?php

namespace App\Enums;

enum FulfillmentCenterType: string
{
    case PrescribeRx = 'prescribe_rx';
    case ShipStation = 'shipstation';
    case ThreePl = 'three_pl';
    case Internal = 'internal';
    case Manual = 'manual';
    case CustomApi = 'custom_api';

    public function label(): string
    {
        return match ($this) {
            self::PrescribeRx => 'Prescribe-Rx',
            self::ShipStation => 'ShipStation',
            self::ThreePl => '3PL / Warehouse',
            self::Internal => 'Internal',
            self::Manual => 'Manual',
            self::CustomApi => 'Custom API',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PrescribeRx => 'primary',
            self::ShipStation => 'info',
            self::ThreePl => 'warning',
            self::Internal => 'success',
            self::Manual => 'gray',
            self::CustomApi => 'danger',
        };
    }

    /** Returns true when this type requires API credential fields. */
    public function requiresApiCredentials(): bool
    {
        return match ($this) {
            self::Internal, self::Manual => false,
            default => true,
        };
    }
}
