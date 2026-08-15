<?php

namespace App\Actions\Settings;

use App\Data\Settings\BillingSettingsData;
use App\Settings\BillingSettings;

class UpdateBillingSettingsAction
{
    public function __construct(private BillingSettings $settings) {}

    public function execute(BillingSettingsData $data): BillingSettings
    {
        $this->settings->checkout_path = $data->checkout_path;
        $this->settings->save();

        return $this->settings;
    }
}
