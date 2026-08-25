<?php

namespace App\Filament\Support;

use App\Cms\Blocks\BlockBlueprint;
use App\Cms\Support\SectionChildren;
use App\Services\Cms\BlockRegistry;
use App\Services\Cms\SectionRegistry;
use App\Settings\ThemeSettings;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

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

        $groups[] = Group::make([self::styleSection(), self::layoutSection()])
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
    /**
     * Colour knobs every section type gets, stored in the same `data` payload
     * as the layout ones and classified the same way (LayoutFields::KEYS).
     *
     * Colours are chosen BY NAME from the install's palette (ThemeSettings),
     * never as a hex typed into a section. That is the whole point: the
     * palette is one edit, and retuning "sand" there moves every section
     * using it. A section that stores a hex would have to be found and
     * re-edited by hand at the next rebrand.
     *
     * Unset means "whatever this section type already looked like" — the
     * frontend only emits a class when a knob resolves, so an untouched
     * section keeps its own stylesheet background rather than being reset to
     * transparent.
     */
    public static function styleSection(): Section
    {
        return Section::make('Style')
            ->description('Colours and imagery for this section. Leave everything unset to keep the section type\'s own design.')
            ->collapsed()
            ->columns(2)
            ->components([
                Select::make('style_background_color')
                    ->label('Background colour')
                    ->options(fn (): array => self::paletteOptions())
                    ->placeholder('Section default')
                    ->native(false)
                    ->helperText(fn (): string => self::paletteHelp('Fills the section band edge to edge. Panels and cards inside keep their own styling.')),

                Select::make('style_text_color')
                    ->label('Text colour')
                    ->options(fn (): array => self::paletteOptions())
                    ->placeholder('Section default')
                    ->native(false)
                    ->helperText(fn (): string => self::paletteHelp('Sets the colour copy inherits. Elements this section styles explicitly keep their own colour.')),

                SectionImagePicker::make('style_background_image')
                    ->label('Background image')
                    ->helperText('Sits behind the section, covering the band. Pair it with a background colour so text stays readable while the image loads.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The palette as Select options, keyed by NAME because that is what a
     * section stores. The hex is appended to each label so an operator
     * picking "sand" can tell which colour that is without opening the theme
     * settings in another tab.
     *
     * @return array<string, string>
     */
    private static function paletteOptions(): array
    {
        $options = [];

        foreach (app(ThemeSettings::class)->palette as $entry) {
            $name = $entry['name'] ?? null;

            if (! filled($name)) {
                continue;
            }

            $options[$name] = Str::headline($name).' — '.($entry['color'] ?? '');
        }

        return $options;
    }

    /**
     * An empty palette makes both colour selects look broken, so say where
     * colours come from rather than offering an empty dropdown silently.
     */
    private static function paletteHelp(string $base): string
    {
        return app(ThemeSettings::class)->palette === []
            ? 'No colours defined yet — add them under Settings → Theme → Colour palette, then pick one here.'
            : $base;
    }

    public static function layoutSection(): Section
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

    /**
     * The "Content blocks" Builder a section uses to hold typed children.
     *
     * Filament's Builder is used rather than a Repeater with a type select
     * because its persisted item shape is ALREADY `{type, data}` — the exact
     * child envelope the frontend consumes, with no discriminator to invent
     * and keep in sync. A Repeater has no per-item type; faking one means a
     * select plus visible() on every field of every block.
     *
     * Blocks state-path relative to their own item, so the shared knob
     * panels appended by blockFor() land at
     * `children.{i}.data.style_background_color` with no statePath work.
     *
     * The key is fixed, not a parameter: SectionDataTransformer resolves
     * children positionally at SectionChildren::KEY, so a Builder stored
     * anywhere else would serve raw `{type, data}` items with unresolved
     * media, and their `type` strings would count as authored content.
     *
     * @param  list<string>|null  $only  Restrict to these block slugs.
     */
    public static function children(?array $only = null): Builder
    {
        return Builder::make(SectionChildren::KEY)
            ->label('Content blocks')
            ->helperText('Repeatable pieces inside this section. Each one carries its own style and layout settings.')
            ->blocks(app(BlockRegistry::class)->builderBlocks($only))
            ->collapsible()
            ->collapsed()
            ->reorderable()
            ->addActionLabel('Add block')
            ->columnSpanFull();
    }

    /**
     * One Builder block for a block blueprint: its own fields, then the same
     * Style and Layout panels every section gets.
     *
     * Reusing styleSection()/layoutSection() verbatim is deliberate — a knob
     * that means one thing on a section and another on a child would be the
     * "control that lies to the operator" shape this system keeps fixing.
     */
    public static function blockFor(BlockBlueprint $blueprint): Block
    {
        return Block::make($blueprint->type())
            ->label($blueprint->label())
            ->icon($blueprint->icon())
            ->schema([
                ...$blueprint->formSchema(),
                self::styleSection(),
                self::layoutSection(),
            ])
            ->columns(2);
    }
}
