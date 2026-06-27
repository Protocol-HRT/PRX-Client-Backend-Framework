<?php

namespace App\Enums;

enum BillingPeriod: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case NineMonth = 'nine_month';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'One-time',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::SemiAnnual => 'Semi-annual',
            self::NineMonth => '9-Month',
            self::Annual => 'Annual',
        };
    }

    public function suffix(): string
    {
        return match ($this) {
            self::OneTime => '',
            self::Monthly => '/mo',
            self::Quarterly => '/qtr',
            self::SemiAnnual => '/6mo',
            self::NineMonth => '/9mo',
            self::Annual => '/yr',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::OneTime => 0,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnual => 6,
            self::NineMonth => 9,
            self::Annual => 12,
        };
    }
}
