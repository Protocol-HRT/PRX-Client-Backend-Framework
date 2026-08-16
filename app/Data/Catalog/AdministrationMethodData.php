<?php

namespace App\Data\Catalog;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class AdministrationMethodData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Max(255)]
        public ?string $slug = null,
        #[Max(16)]
        public ?string $abbreviation = null,
        #[Max(5000)]
        public ?string $description = null,
        public bool $is_active = true,
        public int $position = 0,
        #[Min(0)]
        public ?int $provider_value = null,
    ) {}
}
