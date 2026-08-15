<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ThemeSettings extends Settings
{
    public string $primary_color;

    public string $accent_color;

    public string $accent_secondary_color;

    public string $background_color;

    public string $text_color;

    public string $font_display;

    public string $font_body;

    public ?string $custom_css;

    public string $frontend_template;

    public static function group(): string
    {
        return 'theme';
    }
}
