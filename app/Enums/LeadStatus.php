<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New_ = 'new';
    case HandedOff = 'handed_off';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::New_ => 'New',
            self::HandedOff => 'Handed off to PRX',
            self::Completed => 'Completed',
            self::Abandoned => 'Abandoned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New_ => 'gray',
            self::HandedOff => 'warning',
            self::Completed => 'success',
            self::Abandoned => 'danger',
        };
    }
}
