<?php

namespace App\Data\Catalog;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class ProductClassData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Max(255)]
        public ?string $slug = null,
        #[Max(64)]
        public ?string $short_name = null,
        #[Max(5000)]
        public ?string $description = null,
        #[Max(64)]
        public ?string $icon = null,
        public bool $is_active = true,
        public int $position = 0,
        #[Uuid, Max(36)]
        public ?string $provider_product_class_id = null,
    ) {}
}
