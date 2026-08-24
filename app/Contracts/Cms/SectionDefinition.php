<?php

namespace App\Contracts\Cms;

use Filament\Schemas\Components\Component;

/**
 * A renderable section type the page builder can offer, regardless of whether
 * it is defined in code (SectionBlueprint) or by an admin (FlexibleSectionType).
 */
interface SectionDefinition
{
    /** The string stored in page_sections.type (e.g. "hero", "trust-badges"). */
    public function type(): string;

    /** Admin-facing name shown in selects and table badges. */
    public function label(): string;

    /** Heroicon name for admin UI. */
    public function icon(): string;

    public function description(): ?string;

    /**
     * Initial data payload when an editor adds a section of this type.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * Design defaults for the shared layout knobs, applied where the operator
     * left one unset. Kept apart from defaults() on purpose: defaults() is
     * stamped into a row at creation time and frozen there, whereas these are
     * merged when the payload is served, so retuning a type's design default
     * reaches sections that already exist.
     *
     * @return array<string, string>
     */
    public function layoutDefaults(): array;

    /**
     * Filament form components editing this section's `data` payload.
     *
     * @return array<int, Component>
     */
    public function formSchema(): array;

    /**
     * Map of data key (dot syntax allowed for repeater children) => field kind
     * for keys needing API-side transformation (media resolution, catalog inlining).
     *
     * @return array<string, string>
     */
    public function fieldKinds(): array;

    /**
     * Section-level payload hook, called after per-key field-kind transformation.
     * Lets rich blueprints compute derived content (e.g. run a product query for
     * the configured mode and inline the result).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveData(array $data): array;

    /**
     * Keys in `data` that describe how the section looks rather than what it
     * says — layout knobs and structural flags. A payload holding nothing but
     * these was never authored, so the section renders nothing.
     *
     * @return list<string>
     */
    public function presentationKeys(): array;

    /** True when the definition is DB-backed (admin-created), not code-backed. */
    public function isFlexible(): bool;
}
