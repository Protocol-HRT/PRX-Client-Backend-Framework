<?php

namespace App\Data\Cms;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class GlobalSectionData extends Data
{
    public function __construct(
        #[Required, Max(120)]
        public string $name,
        #[Required, Max(64), Regex('/^[a-z][a-z0-9-]*$/')]
        public string $slug,
        #[Required, Max(64)]
        public string $type,
        public ?array $data = null,
        public bool $enabled = true,
    ) {}
}
