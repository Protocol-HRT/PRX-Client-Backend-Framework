<?php

namespace App\Data\Pages;

use App\Enums\PageStatus;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class PageData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $title,
        #[Required, Max(255)]
        public string $slug,
        #[WithCast(EnumCast::class)]
        public PageStatus $status,
        #[Required, Max(64)]
        public string $template = 'default',
        #[Max(255)]
        public ?string $meta_title = null,
        #[Max(500)]
        public ?string $meta_description = null,
        #[Max(2048)]
        public ?string $og_image_path = null,
        public bool $noindex = false,
        public ?string $publish_at = null,
        /** @var array{enabled?: bool, background_image?: int|string|null, title_override?: string|null, subtitle?: string|null, intro_text?: string|null, show_breadcrumbs?: bool}|null */
        public ?array $title_banner = null,
    ) {}
}
