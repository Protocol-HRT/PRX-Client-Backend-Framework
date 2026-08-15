<?php

namespace App\Cms;

use App\Contracts\Cms\SectionDefinition;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\FlexibleSchemaFormBuilder;

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
        $kinds = [];

        foreach ($this->model->fields() as $field) {
            $kind = $field['kind'] ?? 'text';

            if (in_array($kind, ['image', 'products', 'packages', 'svg'], true)) {
                $kinds[$field['key']] = $kind;
            }

            if ($kind === 'repeater') {
                foreach ($field['fields'] ?? [] as $child) {
                    $childKind = $child['kind'] ?? 'text';

                    if (in_array($childKind, ['image', 'products', 'packages', 'svg'], true)) {
                        $kinds[$field['key'].'.*.'.$child['key']] = $childKind;
                    }
                }
            }
        }

        return $kinds;
    }

    public function resolveData(array $data): array
    {
        return $data;
    }

    public function isFlexible(): bool
    {
        return true;
    }

    /**
     * Compact field-kind map emitted to the API so the frontend's generic
     * renderer knows what each value is: key => {kind} or, for repeaters,
     * key => {kind: "repeater", fields: {childKey => {kind}}}.
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
                    $children[$child['key']] = ['kind' => $child['kind'] ?? 'text'];
                }

                $map[$field['key']] = ['kind' => 'repeater', 'fields' => $children];

                continue;
            }

            $map[$field['key']] = ['kind' => $kind];
        }

        return $map;
    }
}
