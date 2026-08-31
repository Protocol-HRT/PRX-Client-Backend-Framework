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
 * Code blueprints and DB-defined FlexibleSectionType rows share the
 * SectionDefinition contract. Precedence: an ACTIVE DB row wins on slug
 * collision — that's how a seeded code type is promoted to data-driven
 * after golden-parity checks. SHADOW rows (seeded mirrors awaiting
 * promotion) are skipped, so their code definition keeps serving. Admins
 * cannot author over a code slug (reservedSlugs() at creation time); only
 * the seeder introduces colliding rows, in shadow mode.
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
            if ($type->isShadow()) {
                continue;
            }

            $definitions[$type->slug] = new FlexibleDefinition($type);
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
    /**
     * @param  string|null  $context  'page' or 'catalog'; null offers everything.
     */
    public function options(?string $context = null): array
    {
        // Archived flexible types stay resolvable (existing sections keep
        // rendering) but are withheld from the picker so nothing new is
        // authored against them.
        //
        // Context works the same way — it filters the PICKER, never
        // resolution, so a section already authored against a type keeps
        // rendering whatever its contexts later say.
        //
        // Today it only keeps the item-scoped types (`item-faqs`,
        // `item-reviews`) off CMS pages, which have no record for them to
        // read. Every other blueprint still declares both contexts, so a
        // product's picker continues to offer `hero`, `quiz` and the rest —
        // narrowing those is a pending decision, not something this does.
        $available = array_filter(
            $this->all(),
            fn (SectionDefinition $definition): bool => ! ($definition instanceof FlexibleDefinition
                && $definition->model()->isArchived())
                && ($context === null || in_array($context, $definition->contexts(), true)),
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
