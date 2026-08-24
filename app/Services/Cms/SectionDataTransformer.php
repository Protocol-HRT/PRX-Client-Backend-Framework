<?php

namespace App\Services\Cms;

use App\Cms\FlexibleDefinition;
use App\Cms\Support\LayoutFields;
use App\Cms\Support\SectionContent;
use App\Contracts\Cms\SectionDefinition;
use App\Models\Catalog\CatalogItemSection;
use App\Models\Cms\GlobalSection;
use App\Models\PageSection;
use Illuminate\Support\Collection;

/**
 * Turns raw section rows into the frontend section envelope:
 *
 *   { type, origin: code|flexible, anchor, global, has_content, data }
 *
 * Values whose keys are declared in the definition's fieldKinds() are
 * transformed API-side (media ids -> url objects; later: catalog inlining).
 * Two passes keep it one query per resource kind: collect every referenced
 * id first, batch-load, then rewrite.
 *
 * Disabled sections and sections whose type no longer resolves are skipped.
 */
class SectionDataTransformer
{
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly MediaResolver $media,
        private readonly CatalogInliner $catalog,
    ) {}

    /**
     * Accepts page sections and catalog item sections — the two models share
     * the attribute surface this transformer reads (type, data, enabled,
     * anchor_id, globalSection).
     *
     * @param  Collection<int, PageSection|CatalogItemSection>  $sections
     * @return list<array<string, mixed>>
     */
    public function transform(Collection $sections): array
    {
        $prepared = [];

        foreach ($sections as $section) {
            if (! $section->enabled) {
                continue;
            }

            // Global indirection: the referenced global supplies type + data.
            $global = $section->global_section_id !== null ? $section->globalSection : null;

            if ($section->global_section_id !== null && ($global === null || ! $global->enabled)) {
                continue;
            }

            $definition = $this->registry->resolve($global->type ?? $section->type);

            if ($definition === null) {
                continue;
            }

            $prepared[] = [
                'section' => $section,
                'definition' => $definition,
                'global' => $global,
                'data' => ($global !== null ? $global->data : $section->data) ?? [],
            ];
        }

        $this->primeMedia($prepared);

        return array_map(function (array $row): array {
            $data = $row['data'];

            foreach ($this->fieldKinds($row['definition']) as $path => $kind) {
                $this->walk($data, explode('.', $path), fn (mixed $value): mixed => $this->transformValue($kind, $value));
            }

            $data = $row['definition']->resolveData($data);

            // Layout defaults fill only knobs the operator left unset, and
            // only keys in LayoutFields::KEYS, so has_content below is
            // unaffected — every merged key is a presentation key.
            $data = LayoutFields::applyDefaults($data, $row['definition']->layoutDefaults());

            $envelope = [
                'type' => $row['definition']->type(),
                'origin' => $row['definition']->isFlexible() ? 'flexible' : 'code',
                'anchor' => $row['section']->anchor_id,
                'global' => $row['global'] !== null ? [
                    'id' => $row['global']->id,
                    'slug' => $row['global']->slug,
                    'name' => $row['global']->name,
                ] : null,
                // Computed after resolveData() so inlined catalog results count
                // as the content they are. Consumers render nothing when false
                // rather than each reimplementing the walk.
                'has_content' => SectionContent::hasContent($data, $row['definition']->presentationKeys()),
                'data' => $data,
            ];

            if ($row['definition'] instanceof FlexibleDefinition) {
                $envelope['schema'] = $row['definition']->schemaMap();
            }

            return $envelope;
        }, $prepared);
    }

    /**
     * Build one section envelope outside a page context (layout regions).
     * Pass the global when the content comes from a global block; returns
     * null when the type doesn't resolve or the global is disabled.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    public function envelopeFor(?string $type, ?array $data, ?GlobalSection $global = null, ?string $anchor = null): ?array
    {
        if ($global !== null) {
            if (! $global->enabled) {
                return null;
            }

            $type = $global->type;
            $data = $global->data;
        }

        $definition = $type !== null ? $this->registry->resolve($type) : null;

        if ($definition === null) {
            return null;
        }

        $data ??= [];

        foreach ($this->fieldKinds($definition) as $path => $kind) {
            $this->walk($data, explode('.', $path), fn (mixed $value): mixed => $this->transformValue($kind, $value));
        }

        $data = $definition->resolveData($data);

        $data = LayoutFields::applyDefaults($data, $definition->layoutDefaults());

        $envelope = [
            'type' => $definition->type(),
            'origin' => $definition->isFlexible() ? 'flexible' : 'code',
            'anchor' => $anchor,
            'global' => $global !== null ? [
                'id' => $global->id,
                'slug' => $global->slug,
                'name' => $global->name,
            ] : null,
            'has_content' => SectionContent::hasContent($data, $definition->presentationKeys()),
            'data' => $data,
        ];

        if ($definition instanceof FlexibleDefinition) {
            $envelope['schema'] = $definition->schemaMap();
        }

        return $envelope;
    }

    /**
     * Batch-load every media id referenced by any prepared section.
     *
     * @param  list<array<string, mixed>>  $prepared
     */
    private function primeMedia(array $prepared): void
    {
        $ids = [];

        foreach ($prepared as $row) {
            foreach ($this->fieldKinds($row['definition']) as $path => $kind) {
                if ($kind !== 'image') {
                    continue;
                }

                $this->walk($row['data'], explode('.', $path), function (mixed $value) use (&$ids): mixed {
                    if (is_numeric($value)) {
                        $ids[] = (int) $value;
                    }

                    return $value;
                });
            }
        }

        $this->media->prime($ids);
    }

    /**
     * A definition's field kinds, plus the layout knobs that hold a media id.
     *
     * The layout panel is injected into every type by SectionFormBuilder and
     * so appears in no blueprint's fieldKinds(). Without this union a
     * background image would be served as the raw id it is stored as, and
     * every consumer would have to resolve media itself.
     *
     * @return array<string, string>
     */
    private function fieldKinds(SectionDefinition $definition): array
    {
        return $definition->fieldKinds() + array_fill_keys(LayoutFields::IMAGE_KEYS, 'image');
    }

    private function transformValue(string $kind, mixed $value): mixed
    {
        return match ($kind) {
            'image' => $this->media->resolve($value),
            'products' => $this->catalog->productsByIds($this->intList($value)),
            'packages' => $this->catalog->packagesByIds($this->intList($value)),
            'product' => $this->catalog->product(is_numeric($value) ? (int) $value : null),
            'package' => $this->catalog->package(is_numeric($value) ? (int) $value : null),
            'svg' => is_string($value) ? SvgSanitizer::sanitize($value) : null,
            default => $value,
        };
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $value): array
    {
        return array_values(array_map('intval', array_filter((array) $value, 'is_numeric')));
    }

    /**
     * Apply $fn to every value at a dot path within $data, where a `*` segment
     * fans out over list items (e.g. "physicians.*.image").
     *
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $segments
     */
    private function walk(array &$data, array $segments, callable $fn): void
    {
        $key = array_shift($segments);

        if ($key === '*') {
            foreach ($data as &$item) {
                if ($segments === []) {
                    $item = $fn($item);
                } elseif (is_array($item)) {
                    $this->walk($item, $segments, $fn);
                }
            }

            return;
        }

        if ($segments === []) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $fn($data[$key]);
            }

            return;
        }

        if (isset($data[$key]) && is_array($data[$key])) {
            $this->walk($data[$key], $segments, $fn);
        }
    }
}
