<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Schemas\Components\Component;

/**
 * Contract every section type implements. One instance per type, container-resolved.
 *
 * type()      — the SectionType enum case
 * label()     — admin-facing name shown in dropdowns / repeater headers
 * icon()      — heroicon name for navigation/repeater
 * component() — Blade component name (without `sections.` prefix), e.g. "hero" → <x-sections.hero />
 * defaults()  — initial $data array shape used when an editor adds the section
 * formSchema()— Filament form components for editing this section's data
 */
abstract class SectionBlueprint
{
    abstract public function type(): SectionType;

    abstract public function label(): string;

    public function icon(): string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public function component(): string
    {
        return 'sections.'.$this->type()->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * Filament form schema components for editing this section's data.
     *
     * @return array<int, Component>
     */
    abstract public function formSchema(): array;

    public function description(): ?string
    {
        return null;
    }
}
