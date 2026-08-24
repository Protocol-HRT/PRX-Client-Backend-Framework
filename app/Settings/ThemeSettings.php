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
     * The install's named colour vocabulary: a list of
     * ['name' => slug, 'color' => css color] rows. Operators name a colour
     * once here and reach for it by name from the section Style controls
     * (background and text) and from rich text as .tx-{name}. Sections store
     * the NAME, never the hex, so retuning a colour here moves every section
     * that uses it. (No @var — spatie/laravel-settings reflects property
     * docblocks for casts and its parser rejects array-shape syntax.)
     */
    public array $palette;

    /**
     * Deprecated alias of $palette, kept because /config still emits it for
     * frontends built before the palette existed. Written in lockstep by
     * UpdateThemeSettingsAction; edit $palette, never this.
     */
    public array $text_classes;

    public static function group(): string
    {
        return 'theme';
    }
}
