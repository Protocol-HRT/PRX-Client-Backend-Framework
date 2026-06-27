<?php

namespace App\Data\Catalog;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class TagData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Max(255)]
        public ?string $slug = null,
        #[Max(32)]
        public ?string $color = null,
        public bool $is_visible = true,
        public int $position = 0,
    ) {}
}
