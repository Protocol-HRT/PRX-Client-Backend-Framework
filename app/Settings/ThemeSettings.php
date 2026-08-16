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

    /**
     * Named text-color utility classes the frontend generates CSS for:
     * a list of ['name' => slug, 'color' => css color] rows. (No @var —
     * spatie/laravel-settings reflects property docblocks for casts and
     * its parser rejects array-shape syntax.)
     */
    public array $text_classes;

    public static function group(): string
    {
        return 'theme';
    }
}
