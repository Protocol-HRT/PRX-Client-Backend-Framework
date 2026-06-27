<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BrandSettings extends Settings
{
    public string $name;

    public string $tagline;

    public string $logo_path;

    public string $favicon_path;

    public ?string $hero_image_path;

    /** Top-of-page announcement bar (rendered above the nav). */
    public bool $announcement_enabled = true;

    /** Optional sage-green emphasis run at the start of the announcement. */
    public ?string $announcement_emphasis = null;

    /** Body text after the emphasis. Plain text, no HTML. */
    public ?string $announcement_text = null;

    public static function group(): string
    {
        return 'brand';
    }
}
