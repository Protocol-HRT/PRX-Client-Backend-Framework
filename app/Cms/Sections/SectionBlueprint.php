<?php

namespace App\Cms\Sections;

use App\Cms\Concerns\DeclaresPresentationKeys;
use App\Cms\Support\LayoutDefaults;
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
    use DeclaresPresentationKeys;

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
     * Design defaults for the shared layout knobs, looked up by type so the
     * seeded shadow definitions can read the same table and stay in parity.
     * Override only for a type whose default cannot be expressed there.
     *
     * @return array<string, string>
     */
    public function layoutDefaults(): array
    {
        return LayoutDefaults::for($this->type()->value);
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

    /**
     * Map of data key (dot syntax for repeater children, e.g. "items.image") => field kind
     * for keys whose values need API-side transformation (media id resolution,
     * catalog inlining). Blueprints opt in per field; default is no transformation.
     *
     * @return array<string, string>
     */
    public function fieldKinds(): array
    {
        return [];
    }

    /**
     * Section-level API payload hook, called after per-key field-kind
     * transformation. Override to compute derived content (e.g. inline the
     * products matching this section's query mode). Default: pass-through.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveData(array $data): array
    {
        return $data;
    }
}
