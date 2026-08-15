<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SeoSettings extends Settings
{
    public string $default_meta_title;

    public string $default_meta_description;

    public ?string $og_image_path;

    public ?string $google_analytics_id;

    public ?string $google_tag_manager_id;

    public ?string $facebook_pixel_id;

    public ?string $tiktok_pixel_id;

    public ?string $custom_head_scripts;

    public ?string $custom_body_scripts;

    public bool $allow_indexing;

    public static function group(): string
    {
        return 'seo';
    }
}
