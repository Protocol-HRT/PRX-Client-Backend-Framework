<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\CommunicationSettingsData;
use App\Settings\CommunicationSettings;
use Illuminate\Support\Facades\Cache;

class UpdateCommunicationSettingsAction
{
    use Transacts;

    public function __construct(private CommunicationSettings $settings) {}

    public function execute(CommunicationSettingsData $data): CommunicationSettings
    {
        return $this->tx(function () use ($data) {
            $this->settings->twilio_account_sid = $data->twilio_account_sid;
            $this->settings->twilio_auth_token = $data->twilio_auth_token;
            $this->settings->twilio_from_number = $data->twilio_from_number;
            $this->settings->sms_enabled = $data->sms_enabled;
            $this->settings->sms_opt_in_message = $data->sms_opt_in_message;
            $this->settings->voice_enabled = $data->voice_enabled;
            $this->settings->video_enabled = $data->video_enabled;
            $this->settings->save();

            // Provider capabilities in the public config bundle can depend on
            // communication toggles — drop the cached copy after every save.
            Cache::forget('api.v1.config');

            return $this->settings;
        });
    }
}
