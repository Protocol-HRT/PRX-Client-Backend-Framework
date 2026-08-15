<?php

namespace App\Data\Cms;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class MenuData extends Data
{
    public function __construct(
        #[Required, Max(120)]
        public string $name,
        #[Required, Max(64), Regex('/^[a-z][a-z0-9-]*$/')]
        public string $slug,
        #[Max(500)]
        public ?string $description = null,
    ) {}
}
