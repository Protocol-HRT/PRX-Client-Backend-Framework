<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\ThemeSettingsData;
use App\Settings\ThemeSettings;
use Illuminate\Support\Facades\Cache;

class UpdateThemeSettingsAction
{
    use Transacts;

    public function __construct(private ThemeSettings $settings) {}

    public function execute(ThemeSettingsData $data): ThemeSettings
    {
        return $this->tx(function () use ($data) {
            $this->settings->primary_color = $data->primary_color;
            $this->settings->accent_color = $data->accent_color;
            $this->settings->accent_secondary_color = $data->accent_secondary_color;
            $this->settings->background_color = $data->background_color;
            $this->settings->text_color = $data->text_color;
            $this->settings->font_display = $data->font_display;
            $this->settings->font_body = $data->font_body;
            $this->settings->custom_css = $data->custom_css;
            $this->settings->frontend_template = $data->frontend_template;
            $palette = array_values(array_filter(
                $data->palette,
                fn (array $entry): bool => filled($entry['name'] ?? null) && filled($entry['color'] ?? null),
            ));

            $this->settings->palette = $palette;

            // Kept in lockstep, not edited: /config still emits text_classes
            // for frontends built before the palette existed, and the two
            // carry the same {name, color} shape. Drop this mirror only once
            // no consumer reads text_classes.
            $this->settings->text_classes = $palette;
            $this->settings->save();

            // The public config bundle exposes these settings — drop the
            // cached copy so the frontend sees the change on its next boot call.
            Cache::forget('api.v1.config');

            return $this->settings;
        });
    }
}
