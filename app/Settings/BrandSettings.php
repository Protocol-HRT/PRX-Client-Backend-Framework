<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BrandSettings extends Settings
{
    public string $name;

    public string $tagline;

    public ?string $logo_path = null;

    /** Dark-mode / dark-background logo variant. */
    public ?string $logo_dark_path = null;

    /** Light-mode / white-background logo variant. */
    public ?string $logo_light_path = null;

    public ?string $favicon_path = null;

    public ?string $hero_image_path = null;

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
