<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\SeoSettingsData;
use App\Services\Cms\ConfigCache;
use App\Settings\SeoSettings;

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

            // Invalidates BOTH caches between here and a visitor: this app's
            // own config entry and the decoupled frontend's fetch cache.
            // Clearing only the first left an edit invisible for the whole
            // ISR window — see ConfigCache.
            ConfigCache::invalidate();

            return $this->settings;
        });
    }
}
