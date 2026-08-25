<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Cms\Support\PaletteUsage;
use App\Data\Settings\ThemeSettingsData;
use App\Services\Cms\ConfigCache;
use App\Settings\ThemeSettings;
use RuntimeException;

class UpdateThemeSettingsAction
{
    use Transacts;

    public function __construct(private ThemeSettings $settings) {}

    public function execute(ThemeSettingsData $data): ThemeSettings
    {
        $palette = array_values(array_filter(
            $data->palette,
            fn (array $entry): bool => filled($entry['name'] ?? null) && filled($entry['color'] ?? null),
        ));

        // BEFORE the first assignment, not inside the transaction. ThemeSettings
        // is a container singleton, so a rollback undoes the database write but
        // NOT the in-memory object: guarding after the assignments left a
        // refused save holding uncommitted values that anything later in the
        // same request would read back as real. Caught by
        // PaletteDeletionGuardTest::test_a_refused_save_leaves_the_other_theme_fields_untouched.
        $this->guardAgainstRemovingUsedColors($palette);

        return $this->tx(function () use ($data, $palette) {
            $this->settings->primary_color = $data->primary_color;
            $this->settings->accent_color = $data->accent_color;
            $this->settings->accent_secondary_color = $data->accent_secondary_color;
            $this->settings->background_color = $data->background_color;
            $this->settings->text_color = $data->text_color;
            $this->settings->font_display = $data->font_display;
            $this->settings->font_body = $data->font_body;
            $this->settings->custom_css = $data->custom_css;
            $this->settings->frontend_template = $data->frontend_template;
            $this->settings->palette = $palette;

            // Kept in lockstep, not edited: /config still emits text_classes
            // for frontends built before the palette existed, and the two
            // carry the same {name, color} shape. Drop this mirror only once
            // no consumer reads text_classes.
            $this->settings->text_classes = $palette;
            $this->settings->save();

            // Invalidates BOTH caches between here and a visitor: this app's
            // own config entry and the decoupled frontend's fetch cache.
            // Clearing only the first left an edit invisible for the whole
            // ISR window — see ConfigCache.
            ConfigCache::invalidate();

            return $this->settings;
        });
    }

    /**
     * Refuse a save that would drop a colour some section still stores.
     *
     * Guarded twice, the same way the reserved `children` key is: the Repeater
     * carries a matching rule so the operator sees the problem on the field
     * they touched, and the block sits here too, because a form rule is blind
     * to any other caller — a console command, a seeder, an import — and the
     * damage this prevents is invisible until someone loads the live page.
     *
     * Comparison is by NAME, and removal means "gone from the new list",
     * which makes a rename a removal. That is not a technicality: sections
     * store the name, so renaming breaks them exactly as deleting does. See
     * PaletteUsage for why the check is a PHP walk rather than a query.
     *
     * Call this before touching the settings object; see the note at the call
     * site for why a rollback is not enough on its own.
     *
     * @param  list<array{name: string, color: string}>  $palette
     */
    private function guardAgainstRemovingUsedColors(array $palette): void
    {
        $existing = array_column($this->settings->palette ?? [], 'name');
        $removed = array_values(array_diff($existing, array_column($palette, 'name')));

        if ($removed === []) {
            return;
        }

        $usages = PaletteUsage::find($removed);

        if ($usages !== []) {
            throw new RuntimeException(PaletteUsage::message($usages));
        }
    }
}
