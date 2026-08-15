<?php

namespace App\Enums\Cms;

enum RegionItemKind: string
{
    case Section = 'section';
    case GlobalSection = 'global_section';
    case Menu = 'menu';

    public function label(): string
    {
        return match ($this) {
            self::Section => 'Section (owned by this region)',
            self::GlobalSection => 'Global block',
            self::Menu => 'Menu',
        };
    }
}
