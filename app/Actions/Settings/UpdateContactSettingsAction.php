<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\ContactSettingsData;
use App\Settings\ContactSettings;
use Illuminate\Support\Facades\Cache;

class UpdateContactSettingsAction
{
    use Transacts;

    public function __construct(private ContactSettings $settings) {}

    public function execute(ContactSettingsData $data): ContactSettings
    {
        return $this->tx(function () use ($data) {
            $this->settings->support_email = $data->support_email;
            $this->settings->sales_email = $data->sales_email;
            $this->settings->phone = $data->phone;
            $this->settings->address_line_1 = $data->address_line_1;
            $this->settings->address_line_2 = $data->address_line_2;
            $this->settings->city = $data->city;
            $this->settings->state = $data->state;
            $this->settings->postal_code = $data->postal_code;
            $this->settings->country = $data->country;
            $this->settings->business_hours = $data->business_hours;
            $this->settings->instagram_url = $data->instagram_url;
            $this->settings->twitter_url = $data->twitter_url;
            $this->settings->facebook_url = $data->facebook_url;
            $this->settings->linkedin_url = $data->linkedin_url;
            $this->settings->tiktok_url = $data->tiktok_url;
            $this->settings->youtube_url = $data->youtube_url;
            $this->settings->save();

            // The public config bundle exposes these settings — drop the
            // cached copy so the frontend sees the change on its next boot call.
            Cache::forget('api.v1.config');

            return $this->settings;
        });
    }
}
