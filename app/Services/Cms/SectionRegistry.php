<?php

namespace App\Services\Cms;

use App\Cms\BlueprintDefinition;
use App\Cms\FlexibleDefinition;
use App\Contracts\Cms\SectionDefinition;
use App\Enums\SectionType;
use App\Models\Cms\FlexibleSectionType;
use Illuminate\Support\Facades\Schema;

/**
 * Single lookup point for every section type the page builder can offer.
 *
 * Phase 0: code-defined blueprints only. Phase 3 merges DB-defined
 * FlexibleSectionType rows behind the same SectionDefinition contract —
 * code definitions always win on slug collision, and flexible slugs are
 * validated against reservedSlugs() at authoring time.
 *
 * Registered as a singleton so the definition map is built once per request.
 */
class SectionRegistry
{
    /** @var array<string, SectionDefinition>|null */
    private ?array $definitions = null;

    /**
     * All available definitions keyed by type string, code-defined first.
     *
     * @return array<string, SectionDefinition>
     */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];

        foreach (SectionType::cases() as $case) {
            $definitions[$case->value] = new BlueprintDefinition($case->blueprint());
        }

        foreach ($this->flexibleTypes() as $type) {
            // Code definitions win on slug collision (reserved slugs are also
            // rejected at authoring time — this is the belt to that suspender).
            $definitions[$type->slug] ??= new FlexibleDefinition($type);
        }

        return $this->definitions = $definitions;
    }

    /**
     * @return list<FlexibleSectionType>
     */
    private function flexibleTypes(): array
    {
        // Tolerate early boot / pre-migration contexts (e.g. package discovery).
        if (! Schema::hasTable('flexible_section_types')) {
            return [];
        }

        return FlexibleSectionType::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * Drop the memoized map (used after authoring writes within a request).
     */
    public function flush(): void
    {
        $this->definitions = null;
    }

    public function resolve(string $type): ?SectionDefinition
    {
        return $this->all()[$type] ?? null;
    }

    public function exists(string $type): bool
    {
        return $this->resolve($type) !== null;
    }

    /**
     * Type-value => label map for admin selects.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        // Archived flexible types stay resolvable (existing sections keep
        // rendering) but are withheld from the picker so nothing new is
        // authored against them.
        $available = array_filter(
            $this->all(),
            fn (SectionDefinition $definition): bool => ! ($definition instanceof FlexibleDefinition
                && $definition->model()->isArchived()),
        );

        return array_map(
            fn (SectionDefinition $definition): string => $definition->label(),
            $available,
        );
    }

    /**
     * Slugs that admin-defined flexible types may never use.
     *
     * @return list<string>
     */
    public function reservedSlugs(): array
    {
        return array_column(SectionType::cases(), 'value');
    }

    /**
     * Admin-facing label for a type, tolerating types that no longer resolve
     * (e.g. a disabled or deleted flexible type still referenced by old rows).
     */
    public function labelFor(string $type): string
    {
        return $this->resolve($type)?->label()
            ?? str($type)->headline()->toString().' (unavailable)';
    }
}
