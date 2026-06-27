<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\ThemeSettingsData;
use App\Settings\ThemeSettings;

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
            $this->settings->save();

            return $this->settings;
        });
    }
}
