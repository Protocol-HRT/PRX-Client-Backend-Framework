<?php

namespace App\Cms\Sections\Concerns;

use App\Cms\Support\CtaFields;
use Filament\Schemas\Components\Component;

/**
 * Blueprint-side adapter for the shared CTA field group. The actual field
 * definitions, defaults, and inlining live in CtaFields so DB-defined
 * section types (the `cta` field kind) produce identical payloads.
 */
trait HasCatalogCta
{
    /**
     * @return array<string, mixed>
     */
    protected function ctaDefaults(): array
    {
        return CtaFields::defaults();
    }

    /**
     * @return array<int, Component>
     */
    protected function ctaFields(): array
    {
        return CtaFields::components();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveCta(array $data): array
    {
        return CtaFields::resolve($data);
    }
}
