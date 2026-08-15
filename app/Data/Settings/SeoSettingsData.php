<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SeoSettingsData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $default_meta_title,
        #[Required, Max(500)]
        public string $default_meta_description,
        #[Max(2048)]
        public ?string $og_image_path = null,
        #[Max(64)]
        public ?string $google_analytics_id = null,
        #[Max(64)]
        public ?string $google_tag_manager_id = null,
        #[Max(64)]
        public ?string $facebook_pixel_id = null,
        #[Max(64)]
        public ?string $tiktok_pixel_id = null,
        #[Max(10000)]
        public ?string $custom_head_scripts = null,
        #[Max(10000)]
        public ?string $custom_body_scripts = null,
        public bool $allow_indexing = true,
    ) {}
}
