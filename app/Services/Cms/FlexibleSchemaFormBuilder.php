<?php

namespace App\Services\Cms;

use App\Cms\Support\CtaFields;
use App\Cms\Support\VisibleWhen;
use App\Filament\Support\SectionImagePicker;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Turns a FlexibleSectionType's stored field schema into Filament form
 * components — the admin edits flexible-section content through exactly the
 * same modal as code-blueprint sections.
 */
class FlexibleSchemaFormBuilder
{
    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<int, Component>
     */
    public function build(array $fields): array
    {
        return array_map(fn (array $field): Component => $this->component($field), $fields);
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function component(array $field): Component
    {
        $key = $field['key'];
        $label = $field['label'] ?? str($key)->headline()->toString();
        $required = (bool) ($field['required'] ?? false);
        $help = $field['help'] ?? null;

        $component = match ($field['kind'] ?? 'text') {
            'textarea' => Textarea::make($key)->rows(3)->maxLength($field['max'] ?? 2000),
            'richtext' => RichEditor::make($key)->columnSpanFull(),
            'image' => SectionImagePicker::make($key),
            'svg' => Textarea::make($key)
                ->rows(4)
                ->extraAttributes(['class' => 'font-mono text-xs'])
                ->helperText('Inline SVG markup. Scripts and event handlers are stripped on save.'),
            'link' => Fieldset::make($label)
                ->statePath($key)
                ->components([
                    TextInput::make('label')->maxLength(120),
                    TextInput::make('url')->maxLength(2048),
                    Select::make('target')
                        ->options(['_self' => 'Same tab', '_blank' => 'New tab'])
                        ->placeholder('Same tab')
                        ->native(false),
                ])
                ->columns(3),
            'boolean' => Toggle::make($key),
            'select' => Select::make($key)
                ->options(collect($field['options'] ?? [])->pluck('label', 'value')->all())
                ->default($field['default'] ?? null)
                ->native(false),
            'number' => TextInput::make($key)
                ->numeric()
                ->minValue($field['min'] ?? null)
                ->maxValue($field['max'] ?? null)
                ->default($field['default'] ?? null),
            'color' => ColorPicker::make($key),
            'product' => Select::make($key)
                ->searchable()
                ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id')->all()),
            'package' => Select::make($key)
                ->searchable()
                ->options(fn () => Package::published()->orderBy('name')->pluck('name', 'id')->all()),
            'category' => Select::make($key)
                ->searchable()
                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all()),
            'categories' => Select::make($key)
                ->multiple()
                ->searchable()
                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                ->maxItems($field['max'] ?? null),
            'cta' => Fieldset::make($label)
                ->components(CtaFields::components())
                ->columns(2),
            'group' => Fieldset::make($label)
                ->statePath($key)
                ->components($this->build(array_values($field['fields'] ?? [])))
                ->columnSpanFull()
                ->columns(2),
            'repeater' => $this->repeater($key, $field),
            'products' => Select::make($key)
                ->multiple()
                ->searchable()
                ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id')->all())
                ->maxItems($field['max'] ?? null),
            'packages' => Select::make($key)
                ->multiple()
                ->searchable()
                ->options(fn () => Package::published()->orderBy('name')->pluck('name', 'id')->all())
                ->maxItems($field['max'] ?? null),
            default => TextInput::make($key)->maxLength($field['max'] ?? 255),
        };

        if (method_exists($component, 'label') && ! $component instanceof Fieldset) {
            $component->label($label);
        }

        if ($required && method_exists($component, 'required')) {
            $component->required();
        }

        if ($help !== null && method_exists($component, 'helperText') && ($field['kind'] ?? null) !== 'svg') {
            $component->helperText($help);
        }

        $conditions = $field['visible_when'] ?? [];

        if (is_array($conditions) && $conditions !== [] && method_exists($component, 'visible')) {
            $component->visible(fn (Get $get): bool => VisibleWhen::passes($conditions, $get));
        }

        return $component;
    }

    /**
     * Simple repeaters mirror Filament's ->simple() storage: items hold the
     * single child field's bare value instead of a keyed map.
     *
     * @param  array<string, mixed>  $field
     */
    private function repeater(string $key, array $field): Repeater
    {
        $children = array_values($field['fields'] ?? []);

        $repeater = Repeater::make($key)
            ->minItems($field['min'] ?? null)
            ->maxItems($field['max'] ?? null)
            ->reorderable()
            ->columnSpanFull();

        if (($field['simple'] ?? false) && isset($children[0])) {
            /** @var Field $child */
            $child = $this->component($children[0]);

            return $repeater->simple($child);
        }

        return $repeater
            ->schema($this->build($children))
            ->columns(2);
    }
}
