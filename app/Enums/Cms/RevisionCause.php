<?php

namespace App\Enums\Cms;

enum RevisionCause: string
{
    case PageSaved = 'page_saved';
    case SectionsSaved = 'sections_saved';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::PageSaved => 'Page settings edited',
            self::SectionsSaved => 'Sections edited',
            self::Restored => 'Revision restored',
        };
    }
}
