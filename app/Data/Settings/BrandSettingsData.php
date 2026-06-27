<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class BrandSettingsData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Max(255)]
        public string $tagline,
        #[Required, Max(2048)]
        public string $logo_path,
        #[Required, Max(2048)]
        public string $favicon_path,
        #[Max(2048)]
        public ?string $hero_image_path = null,
        public bool $announcement_enabled = true,
        #[Max(120)]
        public ?string $announcement_emphasis = null,
        #[Max(500)]
        public ?string $announcement_text = null,
    ) {}
}
