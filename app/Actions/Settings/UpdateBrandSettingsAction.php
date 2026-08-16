<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\BrandSettingsData;
use App\Settings\BrandSettings;
use Illuminate\Support\Facades\Cache;

class UpdateBrandSettingsAction
{
    use Transacts;

    public function __construct(private BrandSettings $settings) {}

    public function execute(BrandSettingsData $data): BrandSettings
    {
        return $this->tx(function () use ($data) {
            $this->settings->name = $data->name;
            $this->settings->tagline = $data->tagline;
            $this->settings->logo_path = $data->logo_path;
            $this->settings->logo_dark_path = $data->logo_dark_path;
            $this->settings->logo_light_path = $data->logo_light_path;
            $this->settings->favicon_path = $data->favicon_path;
            $this->settings->hero_image_path = $data->hero_image_path;
            $this->settings->announcement_enabled = $data->announcement_enabled;
            $this->settings->announcement_emphasis = $data->announcement_emphasis;
            $this->settings->announcement_text = $data->announcement_text;
            $this->settings->site_url = $data->site_url;
            $this->settings->organization_type = $data->organization_type;
            $this->settings->save();

            // The public config bundle exposes these settings — drop the
            // cached copy so the frontend sees the change on its next boot call.
            Cache::forget('api.v1.config');

            return $this->settings;
        });
    }
}
