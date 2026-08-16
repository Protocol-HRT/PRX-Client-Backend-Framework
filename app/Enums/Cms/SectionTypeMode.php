<?php

namespace App\Enums\Cms;

enum SectionTypeMode: string
{
    /** Live in the registry; overrides a code blueprint on slug collision. */
    case Active = 'active';

    /** Seeded mirror of a code blueprint — inert until promoted after parity checks. */
    case Shadow = 'shadow';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Shadow => 'Shadow (code-backed)',
        };
    }
}
