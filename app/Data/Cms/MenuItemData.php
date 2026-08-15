<?php

namespace App\Data\Cms;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class MenuItemData extends Data
{
    public function __construct(
        public int $menu_id,
        public ?int $parent_id,
        #[Required, Max(120)]
        public string $label,
        #[Required]
        public string $link_type,
        public ?int $linkable_id = null,
        #[Max(2048)]
        public ?string $url = null,
        #[Max(16)]
        public ?string $target = null,
        #[Max(80)]
        public ?string $icon = null,
        #[Max(40)]
        public ?string $badge = null,
        public bool $enabled = true,
    ) {}
}
