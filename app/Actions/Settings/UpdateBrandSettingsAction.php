<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\BrandSettingsData;
use App\Settings\BrandSettings;

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
            $this->settings->favicon_path = $data->favicon_path;
            $this->settings->hero_image_path = $data->hero_image_path;
            $this->settings->save();

            return $this->settings;
        });
    }
}
