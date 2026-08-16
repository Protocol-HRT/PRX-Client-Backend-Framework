<?php

namespace App\Services\Cms;

use App\Cms\FlexibleDefinition;
use App\Cms\Support\CtaFields;
use App\Contracts\Cms\SectionDefinition;
use App\Models\Catalog\CatalogItemSection;
use App\Models\Cms\GlobalSection;
use App\Models\Cms\RegionItem;
use App\Models\PageSection;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read-only reflection over section-type definitions for the admin
 * Section Types catalog: field inventories (walked from the Filament form
 * schema for code blueprints, read from the stored schema for flexible
 * types), usage counts, and example API payloads.
 *
 * All component introspection is defensive — a schema that resists static
 * inspection (closure-driven labels/required) degrades to nulls rather
 * than erroring, since this only feeds an informational UI.
 */
class SectionTypeInspector
{
    public function __construct(
        private readonly SectionDataTransformer $transformer,
    ) {}

    /**
     * Flat field inventory. Repeater children nest under `children`.
     *
     * @return list<array{name: string, label: string, kind: string, required: ?bool, children: list<array{name: string, label: string, kind: string, required: ?bool}>}>
     */
    public function fields(SectionDefinition $definition): array
    {
        if ($definition instanceof FlexibleDefinition) {
            return $this->flexibleFields($definition);
        }

        $out = [];
        $this->walkComponents($definition->formSchema(), $out);

        return $out;
    }

    /**
     * Where sections of this type currently live.
     *
     * @return array{pages: int, catalog: int, globals: int, regions: int, total: int}
     */
    public function usage(string $type): array
    {
        $pages = PageSection::query()->where('type', $type)->whereNull('global_section_id')->count();
        $catalog = CatalogItemSection::query()->where('type', $type)->whereNull('global_section_id')->count();
        $globals = GlobalSection::query()->where('type', $type)->count();
        $regions = RegionItem::query()->where('section_type', $type)->count();

        return [
            'pages' => $pages,
            'catalog' => $catalog,
            'globals' => $globals,
            'regions' => $regions,
            'total' => $pages + $catalog + $globals + $regions,
        ];
    }

    /**
     * Example API envelope for this type, exactly as /api/v1 ships it.
     * Prefers a real section (run through the live transformer); falls back
     * to a synthesized payload built from defaults + per-kind placeholders.
     *
     * @return array{source: string, payload: array<string, mixed>|null}
     */
    public function examplePayload(SectionDefinition $definition): array
    {
        $type = $definition->type();

        $live = PageSection::query()
            ->where('type', $type)
            ->whereNull('global_section_id')
            ->where('enabled', true)
            ->latest('updated_at')
            ->first()
            ?? CatalogItemSection::query()
                ->where('type', $type)
                ->whereNull('global_section_id')
                ->where('enabled', true)
                ->latest('updated_at')
                ->first();

        if ($live !== null) {
            $envelope = $this->transformer->transform(collect([$live]))[0] ?? null;

            if ($envelope !== null) {
                return ['source' => 'live', 'payload' => $envelope];
            }
        }

        $data = $this->synthesizeData($definition);
        $envelope = $this->transformer->envelopeFor($type, $data);

        if ($envelope !== null) {
            $envelope['data'] = $this->injectKindSamples($envelope['data'], $definition->fieldKinds());
        }

        return ['source' => 'synthesized', 'payload' => $envelope];
    }

    /**
     * @return list<array{name: string, label: string, kind: string, required: ?bool, children: list<array{name: string, label: string, kind: string, required: ?bool}>}>
     */
    private function flexibleFields(FlexibleDefinition $definition): array
    {
        $map = fn (array $field): array => [
            'name' => $field['key'] ?? '',
            'label' => filled($field['label'] ?? null) ? $field['label'] : Str::headline($field['key'] ?? ''),
            'kind' => $field['kind'] ?? 'text',
            'required' => (bool) ($field['required'] ?? false),
            'children' => [],
        ];

        $out = [];

        foreach ($definition->model()->fields() as $field) {
            // Group children live at dotted state paths ('peptide_card.title')
            // exactly like code blueprints' dotted field names — flatten them
            // so both origins inventory identically.
            if (($field['kind'] ?? null) === 'group') {
                foreach ($field['fields'] ?? [] as $child) {
                    foreach ($this->flexibleEntries($child, $map) as $entry) {
                        $entry['name'] = ($field['key'] ?? '').'.'.$entry['name'];
                        $out[] = $entry;
                    }
                }

                continue;
            }

            foreach ($this->flexibleEntries($field, $map) as $entry) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * One schema field -> inventory entries. A `cta` kind IS its flat keys
     * (cta_label, cta_mode, …) — expand it exactly as walking the code
     * blueprints' CtaFields::components() does, so both origins inventory
     * identically. Repeater children expand recursively under `children`.
     *
     * @param  array<string, mixed>  $field
     * @param  callable(array<string, mixed>): array{name: string, label: string, kind: string, required: ?bool, children: list<mixed>}  $map
     * @return list<array{name: string, label: string, kind: string, required: ?bool, children: list<mixed>}>
     */
    private function flexibleEntries(array $field, callable $map): array
    {
        if (($field['kind'] ?? null) === 'cta') {
            return array_map(fn (string $key): array => [
                'name' => $key,
                'label' => Str::headline($key),
                'kind' => in_array($key, ['cta_mode', 'cta_item_type', 'cta_product_id', 'cta_package_id'], true) ? 'select' : 'text',
                'required' => false,
                'children' => [],
            ], CtaFields::KEYS);
        }

        $entry = $map($field);

        foreach ($field['fields'] ?? [] as $child) {
            foreach ($this->flexibleEntries($child, $map) as $childEntry) {
                $entry['children'][] = $childEntry;
            }
        }

        return [$entry];
    }

    /**
     * @param  array<int, mixed>  $components
     * @param  list<array<string, mixed>>  $out
     */
    private function walkComponents(array $components, array &$out): void
    {
        foreach ($components as $component) {
            if ($component instanceof Field) {
                $entry = [
                    'name' => $component->getName(),
                    'label' => $this->componentLabel($component),
                    'kind' => $this->componentKind($component),
                    'required' => $this->componentRequired($component),
                    'children' => [],
                ];

                if ($component instanceof Repeater) {
                    $children = [];
                    $this->walkComponents($this->childComponents($component), $children);
                    $entry['children'] = $children;
                }

                $out[] = $entry;

                continue;
            }

            $this->walkComponents($this->childComponents($component), $out);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function childComponents(mixed $component): array
    {
        foreach (['getDefaultChildComponents', 'getChildComponents'] as $method) {
            if (is_object($component) && method_exists($component, $method)) {
                try {
                    $children = $component->{$method}();
                } catch (Throwable) {
                    continue;
                }

                if (is_array($children)) {
                    return $children;
                }
            }
        }

        return [];
    }

    private function componentLabel(Field $component): string
    {
        try {
            $label = $component->getLabel();

            if (is_string($label) && $label !== '') {
                return $label;
            }
        } catch (Throwable) {
            // Closure-driven label needing live state — fall through.
        }

        return Str::headline($component->getName());
    }

    private function componentKind(Field $component): string
    {
        return Str::of(class_basename($component))
            ->snake(' ')
            ->replace('section image picker', 'image')
            ->replace('curator picker', 'image')
            ->replace('file upload', 'image')
            ->replace('rich editor', 'rich text')
            ->replace('text input', 'text')
            ->toString();
    }

    private function componentRequired(Field $component): ?bool
    {
        try {
            return (bool) $component->isRequired();
        } catch (Throwable) {
            return null; // Conditional — depends on live form state.
        }
    }

    /**
     * Defaults overlaid with readable placeholders for blank scalar values.
     *
     * @return array<string, mixed>
     */
    private function synthesizeData(SectionDefinition $definition): array
    {
        $data = $definition->defaults();

        foreach ($this->fields($definition) as $field) {
            $name = $field['name'];

            if ($name === '' || str_contains($name, '.')) {
                continue;
            }

            $current = $data[$name] ?? null;

            if ($field['kind'] === 'repeater') {
                if (blank($current)) {
                    $item = [];

                    foreach ($field['children'] as $child) {
                        $item[$child['name']] = $this->placeholderFor($child['kind'], $child['label']);
                    }

                    $data[$name] = $item === [] ? [] : [$item, $item];
                }

                continue;
            }

            if (blank($current) && ! is_bool($current)) {
                $data[$name] = $this->placeholderFor($field['kind'], $field['label']);
            }
        }

        return $data;
    }

    private function placeholderFor(string $kind, string $label): mixed
    {
        return match ($kind) {
            'toggle', 'checkbox' => false,
            'rich text' => '<p>Example '.Str::lower($label).' content.</p>',
            'textarea' => 'Example '.Str::lower($label).' copy shown on the storefront.',
            'image', 'svg' => null, // Replaced with a media-shaped sample after the envelope pass.
            'select' => null,       // Blueprint defaults usually carry the real option value.
            'number', 'color', 'category', 'cta' => null,
            'repeater' => [],
            'products', 'packages' => [],
            'product', 'package' => null,
            default => 'Example '.Str::lower($label),
        };
    }

    /**
     * The synthesized path has no real media/catalog rows to resolve, so
     * inject samples matching the shapes MediaResolver / the catalog
     * inliner produce for real content.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $fieldKinds
     * @return array<string, mixed>
     */
    private function injectKindSamples(array $data, array $fieldKinds): array
    {
        foreach ($fieldKinds as $path => $kind) {
            $sample = match ($kind) {
                'image' => [
                    'id' => 1,
                    'url' => url('/storage/media/example-image.jpg'),
                    'alt' => 'Example image',
                    'width' => 1200,
                    'height' => 800,
                ],
                'svg' => [
                    'id' => 1,
                    'url' => url('/storage/media/example-icon.svg'),
                    'alt' => 'Example icon',
                    'width' => 48,
                    'height' => 48,
                ],
                'products' => ['(inlined product summaries — add a live section of this type to see the full shape)'],
                'packages' => ['(inlined package summaries — add a live section of this type to see the full shape)'],
                'product' => '(inlined product summary — add a live section of this type to see the full shape)',
                'package' => '(inlined package summary — add a live section of this type to see the full shape)',
                default => null,
            };

            if ($sample === null) {
                continue;
            }

            $this->setPath($data, explode('.', $path), $sample);
        }

        return $data;
    }

    /**
     * Set a value at a dot path where `*` fans out over list items, only
     * touching slots that exist and are currently blank.
     *
     * @param  array<string, mixed>  $target
     * @param  list<string>  $segments
     */
    private function setPath(array &$target, array $segments, mixed $value): void
    {
        $segment = array_shift($segments);

        if ($segment === null) {
            return;
        }

        if ($segment === '*') {
            foreach ($target as &$item) {
                if (is_array($item)) {
                    $this->setPath($item, $segments, $value);
                }
            }

            return;
        }

        if ($segments === []) {
            if (array_key_exists($segment, $target) && blank($target[$segment])) {
                $target[$segment] = $value;
            }

            return;
        }

        if (isset($target[$segment]) && is_array($target[$segment])) {
            $this->setPath($target[$segment], $segments, $value);
        }
    }
}
