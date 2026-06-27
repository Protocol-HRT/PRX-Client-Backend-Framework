<?php

namespace App\Enums;

enum RebillStrategy: string
{
    case AutoRenew = 'auto_renew';
    case PatientChoice = 'patient_choice';

    public function label(): string
    {
        return match ($this) {
            self::AutoRenew => 'Auto-renew',
            self::PatientChoice => 'Patient choice',
        };
    }
}
