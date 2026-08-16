<?php

namespace App\Enums;

enum BillingMode: string
{
    case PrepaidTerm = 'prepaid_term';
    case Recurring = 'recurring';
    case Installment = 'installment';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::PrepaidTerm => 'Prepaid Term',
            self::Recurring => 'Recurring',
            self::Installment => 'Installment',
            self::External => 'External',
        };
    }

    /** PRX Package\BillingMode integer backing value. */
    public function providerValue(): int
    {
        return match ($this) {
            self::PrepaidTerm => 1,
            self::Recurring => 2,
            self::Installment => 3,
            self::External => 4,
        };
    }

    public static function fromProviderValue(?int $value): ?self
    {
        return match ($value) {
            1 => self::PrepaidTerm,
            2 => self::Recurring,
            3 => self::Installment,
            4 => self::External,
            default => null,
        };
    }
}
