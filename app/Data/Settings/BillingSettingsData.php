<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class BillingSettingsData extends Data
{
    public function __construct(
        #[Required, In(['prx', 'local'])]
        public string $checkout_path,
    ) {}
}
