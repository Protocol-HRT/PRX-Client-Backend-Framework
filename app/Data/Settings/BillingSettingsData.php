<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class BillingSettingsData extends Data
{
    public function __construct(
        #[Required, In(['prx', 'local'])]
        public string $checkout_path,

        public bool $upsells_enabled = true,

        #[IntegerType, Min(1), Max(12)]
        public int $upsells_limit = 4,
    ) {}
}
