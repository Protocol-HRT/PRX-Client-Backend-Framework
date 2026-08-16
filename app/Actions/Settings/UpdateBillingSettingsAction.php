<?php

namespace App\Actions\Settings;

use App\Data\Settings\BillingSettingsData;
use App\Settings\BillingSettings;
use Illuminate\Support\Facades\Cache;

class UpdateBillingSettingsAction
{
    public function __construct(private BillingSettings $settings) {}

    public function execute(BillingSettingsData $data): BillingSettings
    {
        $this->settings->checkout_path = $data->checkout_path;
        $this->settings->upsells_enabled = $data->upsells_enabled;
        $this->settings->upsells_limit = $data->upsells_limit;
        $this->settings->save();

        // The public config bundle exposes checkout settings — drop the
        // cached copy so the frontend sees the change on its next boot call.
        Cache::forget('api.v1.config');

        return $this->settings;
    }
}
