<?php

namespace App\Filament\Support;

use App\Services\Cms\SectionRegistry;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Builds the per-section-type Filament form groups shared by every place a
 * section's `data` payload is edited (page sections relation manager — later
 * global sections and region items).
 *
 * One Group per registered type, visible only while that type is selected in
 * a sibling `type` field, with all components nested under the JSON `data`
 * column via statePath.
 */
class SectionFormBuilder
{
    /**
     * @param  string  $typeField  Name of the sibling field holding the selected
     *                             type (e.g. `section_type` on region items).
     * @return array<int, Group>
     */
    public static function typeGroups(string $typeField = 'type'): array
    {
        $groups = [];

        foreach (app(SectionRegistry::class)->all() as $type => $definition) {
            // A Get bound to a Group resolves paths against the group's PARENT
            // container, so the sibling type field is addressed WITHOUT '../'.
            $groups[] = Group::make($definition->formSchema())
                ->statePath('data')
                ->visible(fn (Get $get): bool => $get($typeField) === $type);
        }

        // Shared presentation knobs every section type gets, stored alongside
        // the type-specific fields in the same `data` payload.
        $groups[] = Group::make([
            Select::make('extra_padding')
                ->label('Extra section padding')
                ->options(['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'])
                ->placeholder('None (default)')
                ->native(false)
                ->helperText('Additional vertical breathing room around this section on the public site.'),
        ])
            ->statePath('data')
            ->visible(fn (Get $get): bool => filled($get($typeField)));

        return $groups;
    }
}
