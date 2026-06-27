<?php

namespace App\Enums;

enum EncounterStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Denied = 'denied';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::InReview => 'In review',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Submitted => 'info',
            self::InReview => 'warning',
            self::Approved => 'success',
            self::Denied => 'danger',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }
}
