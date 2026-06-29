<?php

namespace App\Enums;

enum CatalogStatus: string
{
    case Pending = 'pending';
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Draft => 'gray',
            self::Published => 'success',
            self::Archived => 'danger',
        };
    }

    /** Whether this status is visible on the public-facing frontend API. */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
