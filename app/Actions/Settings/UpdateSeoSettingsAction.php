<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\SeoSettingsData;
use App\Settings\SeoSettings;
use Illuminate\Support\Facades\Cache;

class UpdateSeoSettingsAction
{
    use Transacts;

    public function __construct(private SeoSettings $settings) {}

    public function execute(SeoSettingsData $data): SeoSettings
    {
        return $this->tx(function () use ($data) {
            $this->settings->default_meta_title = $data->default_meta_title;
            $this->settings->default_meta_description = $data->default_meta_description;
            $this->settings->og_image_path = $data->og_image_path;
            $this->settings->google_analytics_id = $data->google_analytics_id;
            $this->settings->google_tag_manager_id = $data->google_tag_manager_id;
            $this->settings->facebook_pixel_id = $data->facebook_pixel_id;
            $this->settings->tiktok_pixel_id = $data->tiktok_pixel_id;
            $this->settings->custom_head_scripts = $data->custom_head_scripts;
            $this->settings->custom_body_scripts = $data->custom_body_scripts;
            $this->settings->allow_indexing = $data->allow_indexing;
            $this->settings->save();

            // The public config bundle exposes these settings — drop the
            // cached copy so the frontend sees the change on its next boot call.
            Cache::forget('api.v1.config');

            return $this->settings;
        });
    }
}
