<?php

namespace App\Data\Catalog;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CategoryData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Max(255)]
        public ?string $slug = null,
        public ?int $parent_id = null,
        #[Max(2000)]
        public ?string $description = null,
        #[Max(2048)]
        public ?string $hero_image_path = null,
        #[Max(64)]
        public ?string $icon = null,
        public bool $is_visible = true,
        public int $position = 0,
        #[Max(255)]
        public ?string $meta_title = null,
        #[Max(500)]
        public ?string $meta_description = null,
    ) {}
}
