<?php

namespace App\Enums\Payments;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Succeeded = 'succeeded';
    case Refunded = 'refunded';
    case Voided = 'voided';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Authorized => 'Authorized',
            self::Captured => 'Captured',
            self::Succeeded => 'Succeeded',
            self::Refunded => 'Refunded',
            self::Voided => 'Voided',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Authorized => 'info',
            self::Captured, self::Succeeded => 'success',
            self::Refunded => 'gray',
            self::Voided => 'gray',
            self::Failed => 'danger',
        };
    }
}
