<?php

namespace App\Data\Settings;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ThemeSettingsData extends Data
{
    public function __construct(
        #[Required, Regex('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/')]
        public string $primary_color,
        #[Required, Regex('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/')]
        public string $accent_color,
        #[Required, Regex('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/')]
        public string $accent_secondary_color,
        #[Required, Regex('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/')]
        public string $background_color,
        #[Required, Regex('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/')]
        public string $text_color,
        #[Required]
        public string $font_display,
        #[Required]
        public string $font_body,
        #[Max(50000)]
        public ?string $custom_css = null,
        #[Required, Max(100), Regex('/^[a-z0-9][a-z0-9-]*$/')]
        public string $frontend_template = 'default',
        /** @var array<int, array{name: string, color: string}> */
        public array $text_classes = [],
    ) {}
}
