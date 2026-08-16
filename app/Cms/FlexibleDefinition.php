<?php

namespace App\Cms;

use App\Cms\Support\CtaFields;
use App\Contracts\Cms\SectionDefinition;
use App\Enums\Cms\FlexibleFieldKind;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\FlexibleSchemaFormBuilder;
use App\Services\Cms\SectionResolverOps;

/**
 * Adapts an admin-defined FlexibleSectionType row to the SectionDefinition
 * contract so it lives in the same registry as code blueprints.
 */
class FlexibleDefinition implements SectionDefinition
{
    public function __construct(private readonly FlexibleSectionType $model) {}

    public function model(): FlexibleSectionType
    {
        return $this->model;
    }

    public function type(): string
    {
        return $this->model->slug;
    }

    public function label(): string
    {
        return $this->model->name;
    }

    public function icon(): string
    {
        return $this->model->icon ?: 'heroicon-o-squares-2x2';
    }

    public function description(): ?string
    {
        return $this->model->description;
    }

    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->model->fields() as $field) {
            if (($field['kind'] ?? null) === 'cta') {
                $defaults = array_merge($defaults, CtaFields::defaults());

                continue;
            }

            $defaults[$field['key']] = $field['default']
                ?? (($field['kind'] ?? null) === 'repeater' ? [] : null);
        }

        return $defaults;
    }

    public function formSchema(): array
    {
        return app(FlexibleSchemaFormBuilder::class)->build($this->model->fields());
    }

    public function fieldKinds(): array
    {
        $inline = array_map(
            fn (FlexibleFieldKind $kind): string => $kind->value,
            FlexibleFieldKind::inlineResolvedKinds(),
        );

        $kinds = [];

        foreach ($this->model->fields() as $field) {
            $kind = $field['kind'] ?? 'text';

            if (in_array($kind, $inline, true) && ! ($field['raw'] ?? false)) {
                $kinds[$field['key']] = $kind;
            }

            if ($kind === 'repeater') {
                foreach ($field['fields'] ?? [] as $child) {
                    $childKind = $child['kind'] ?? 'text';

                    if (in_array($childKind, $inline, true) && ! ($child['raw'] ?? false)) {
                        $kinds[$field['key'].'.*.'.$child['key']] = $childKind;
                    }
                }
            }
        }

        return $kinds;
    }

    /**
     * Field-kind transforms already ran (SectionDataTransformer); this pass
     * adds the schema-driven behaviors: select values re-cast to string
     * (Filament re-saves select state as integers), CTA groups resolved to
     * inlined catalog cards, then the type's declarative resolver pipeline.
     */
    public function resolveData(array $data): array
    {
        $data = $this->applySchemaBehaviors($data, $this->model->fields());

        $resolvers = $this->model->schema['resolvers'] ?? [];

        return is_array($resolvers) && $resolvers !== []
            ? app(SectionResolverOps::class)->apply($data, array_values($resolvers))
            : $data;
    }

    public function isFlexible(): bool
    {
        return true;
    }

    /**
     * Compact field-kind map emitted to the API so the frontend's generic
     * renderer knows what each value is: key => {kind} or, for repeaters,
     * key => {kind: "repeater", fields: {childKey => {kind}}}. Fields kept
     * raw for a resolver op are flagged so the renderer doesn't expect an
     * inlined shape.
     *
     * @return array<string, array<string, mixed>>
     */
    public function schemaMap(): array
    {
        $map = [];

        foreach ($this->model->fields() as $field) {
            $kind = $field['kind'] ?? 'text';

            if ($kind === 'repeater') {
                $children = [];

                foreach ($field['fields'] ?? [] as $child) {
                    $children[$child['key']] = $this->mapEntry($child);
                }

                $map[$field['key']] = ['kind' => 'repeater', 'fields' => $children];

                continue;
            }

            $map[$field['key']] = $this->mapEntry($field);
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function mapEntry(array $field): array
    {
        $entry = ['kind' => $field['kind'] ?? 'text'];

        if ($field['raw'] ?? false) {
            $entry['raw'] = true;
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function applySchemaBehaviors(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            $kind = $field['kind'] ?? 'text';

            if ($kind === 'cta') {
                $data = CtaFields::resolve($data);

                continue;
            }

            if ($key === null) {
                continue;
            }

            if ($kind === 'select' && isset($data[$key]) && is_scalar($data[$key])) {
                $data[$key] = (string) $data[$key];
            }

            if ($kind === 'repeater' && isset($data[$key]) && is_array($data[$key])) {
                foreach ($data[$key] as $index => $item) {
                    if (is_array($item)) {
                        $data[$key][$index] = $this->applySchemaBehaviors($item, array_values($field['fields'] ?? []));
                    }
                }
            }
        }

        return $data;
    }
}
