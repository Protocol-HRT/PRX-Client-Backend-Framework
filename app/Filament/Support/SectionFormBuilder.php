<?php

namespace App\Filament\Support;

use App\Services\Cms\SectionRegistry;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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

        $groups[] = Group::make([self::layoutSection()])
            ->statePath('data')
            ->visible(fn (Get $get): bool => filled($get($typeField)));

        return $groups;
    }

    /**
     * Layout knobs every section type gets, stored alongside the
     * type-specific fields in the same `data` payload.
     *
     * Deliberately a fixed vocabulary of sizes rather than free-form pixel
     * values: the frontend maps each to a CSS custom property, so pages stay
     * visually consistent, operators stay out of inline styling, and the
     * scale can be retuned globally without touching content.
     *
     * The frontend applies these as `sx-*` classes on a wrapper around the
     * section (see SectionRenderer). Horizontal inset and width move the
     * section's CONTENT only — backgrounds and hero imagery stay full-bleed.
     */
    private static function layoutSection(): Section
    {
        return Section::make('Layout & spacing')
            ->description('How this section sits on the page. Leave everything unset for the design default.')
            ->collapsed()
            ->columns(2)
            ->components([
                Select::make('extra_padding')
                    ->label('Extra vertical padding')
                    ->options([
                        'sm' => 'Small',
                        'md' => 'Medium',
                        'lg' => 'Large',
                    ])
                    ->placeholder('None (default)')
                    ->native(false)
                    ->helperText('Additional breathing room above and below the section.'),

                Select::make('content_inset')
                    ->label('Horizontal inset')
                    ->options([
                        'sm' => 'Small',
                        'md' => 'Medium',
                        'lg' => 'Large',
                        'xl' => 'Extra large',
                    ])
                    ->placeholder('None (default)')
                    ->native(false)
                    ->helperText('Pulls text and buttons in from the left and right edges. Background images stay full width.'),

                Select::make('content_width')
                    ->label('Content width')
                    ->options([
                        'narrow' => 'Narrow — long-form reading',
                        'medium' => 'Medium',
                        'wide' => 'Wide',
                        'xwide' => 'Extra wide',
                        'full' => 'Full — no cap',
                    ])
                    ->placeholder('Design default')
                    ->native(false)
                    ->helperText('Caps how wide the content runs, centred within the section. Leave unset to use this section type\'s design default.'),

                Select::make('content_align')
                    ->label('Content alignment')
                    ->options([
                        'left' => 'Left',
                        'center' => 'Centre',
                        'right' => 'Right',
                    ])
                    ->placeholder('Design default')
                    ->native(false)
                    ->helperText('Aligns headings, copy and buttons within the section.'),

                Select::make('media_width')
                    ->label('Media width')
                    ->options([
                        'contained' => 'Contained — sits inside the content column',
                        'full' => 'Full bleed — spans the section edge to edge',
                    ])
                    ->placeholder('Design default')
                    ->native(false)
                    ->helperText('How this section\'s image or video is framed. Leave unset to use this section type\'s design default.'),
            ]);
    }
}
