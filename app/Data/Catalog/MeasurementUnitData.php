<?php

namespace App\Data\Catalog;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class MeasurementUnitData extends Data
{
    public function __construct(
        #[Required, Max(64)]
        public string $name,
        #[Required, Max(24)]
        public string $abbreviation,
        public bool $is_active = true,
        public int $position = 0,
        #[Min(0)]
        public ?int $provider_value = null,
    ) {}
}
