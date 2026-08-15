<?php

namespace App\Actions\Settings;

use App\Actions\Concerns\Transacts;
use App\Data\Settings\LlmSettingsData;
use App\Settings\LlmSettings;

class UpdateLlmSettingsAction
{
    use Transacts;

    public function __construct(private LlmSettings $settings) {}

    public function execute(LlmSettingsData $data): LlmSettings
    {
        return $this->tx(function () use ($data) {
            $this->settings->active_provider = $data->active_provider;
            $this->settings->claude_api_key = $data->claude_api_key;
            $this->settings->claude_model = $data->claude_model;
            $this->settings->openai_api_key = $data->openai_api_key;
            $this->settings->openai_model = $data->openai_model;
            $this->settings->save();

            return $this->settings;
        });
    }
}
