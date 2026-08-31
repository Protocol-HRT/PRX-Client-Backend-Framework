<?php

namespace App\Filament\Support;

use App\Settings\ThemeSettings;
use Illuminate\Support\Str;

/**
 * The install's colour palette, as Filament Select options.
 *
 * Extracted from SectionFormBuilder when health-goal badges became the second
 * surface that lets an operator pick a palette colour. One implementation on
 * purpose: a section and a badge that disagreed about what "sand" is called,
 * or that formatted the dropdown differently, would read as two unrelated
 * colour systems in one admin.
 *
 * Options are keyed by NAME, never by hex, because that is what a record
 * stores — see the palette-deletion guard (App\Cms\Support\PaletteUsage),
 * which can only find a colour's users if they name it.
 */
final class PaletteChoices
{
    /**
     * The palette as Select options. The hex is appended to each label so an
     * operator picking "sand" can tell which colour that is without opening
     * the theme settings in another tab.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (app(ThemeSettings::class)->palette as $entry) {
            $name = $entry['name'] ?? null;

            if (! filled($name)) {
                continue;
            }

            $options[$name] = Str::headline($name).' — '.($entry['color'] ?? '');
        }

        return $options;
    }

    /**
     * An empty palette makes a colour select look broken, so say where
     * colours come from rather than offering an empty dropdown silently.
     */
    public static function help(string $base): string
    {
        return app(ThemeSettings::class)->palette === []
            ? 'No colours defined yet — add them under Settings → Theme → Colour palette, then pick one here.'
            : $base;
    }
}
