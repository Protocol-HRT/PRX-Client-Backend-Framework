<?php

namespace App\Filament\Support;

use App\Cms\Blocks\BlockBlueprint;
use App\Cms\Support\SectionChildren;
use App\Services\Cms\BlockRegistry;
use App\Services\Cms\SectionRegistry;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Builds the per-section-type Filament form groups shared by every place a
 * section's `data` payload is edited (page sections relation manager — later
 * global sections and region items).
 *
 * ONE GROUP, RESOLVED FROM THE SELECTED TYPE — not one group per type.
 *
 * It used to build every registered type's group at once, each gated by
 * `visible()`. That is the obvious shape, and it silently corrupted content:
 * **`visible()` hides a component, it does not stop its state cast.** All 27
 * groups bind into the same `data.*` path, so wherever two blueprints spell the
 * same key with different components, the one carrying a cast rewrote the
 * other's value on every load.
 *
 * Three keys collided in practice, each with a RichEditor on one side:
 *
 *   heading     RichEditor in 26 types        vs  TextInput in comparison-table
 *   cta_label   RichEditor in package-slider  vs  TextInput in 12 types
 *   label       RichEditor in 6 types         vs  TextInput in comparison-table
 *
 * `RichEditorStateCast` wraps a plain string into a ProseMirror document, so a
 * heading stored as "Our Peptides Vs. Research-Use-Only" reached a TextInput as
 * an object. The input rendered `[object Object]`, and saving wrote the document
 * back — which is how two live pages ended up holding a document in a field the
 * schema declares as text, and how `[object Object]` reached a visitor inside an
 * <h2>.
 *
 * A fourth key, `icon`, collides across Select / TextInput / CuratorPicker /
 * Textarea. None of those wraps a string today so it did no damage, but it is
 * the same latent fault — which is why the fix is structural rather than a
 * rename of the three that happened to bite.
 *
 * Resolving the schema from the selected type means only that type's components
 * exist, so no other blueprint's cast can reach the payload. It is also far less
 * work per render: this form used to instantiate every field of every blueprint
 * on every load.
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
        return [
            // A Get bound to a Group resolves paths against the group's PARENT
            // container, so the sibling type field is addressed WITHOUT '../'.
            //
            // The schema is a closure so it is rebuilt when the type changes,
            // and `key()` gives Filament a stable identity to swap the contents
            // of. Only the chosen blueprint's components ever exist — see the
            // class doc for what having them all at once did.
            Group::make()
                ->key('section-type-fields')
                ->statePath('data')
                ->schema(function (Get $get) use ($typeField): array {
                    $type = $get($typeField);

                    if (! is_string($type) || $type === '') {
                        return [];
                    }

                    return app(SectionRegistry::class)->resolve($type)?->formSchema() ?? [];
                }),

            Group::make([self::styleSection(), self::layoutSection()])
                ->statePath('data')
                ->visible(fn (Get $get): bool => filled($get($typeField))),
        ];
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
    public static function styleSection(bool $nested = false): Section
    {
        $thing = $nested ? 'block' : 'section';

        return Section::make('Style')
            ->description('Colours and imagery for this '.$thing.'. Leave everything unset to keep its own design.')
            ->collapsed()
            ->columns(2)
            ->components([
                // The colour placeholders deliberately stay "Section default"
                // at BOTH levels, unlike the radius above. An unset colour on a
                // block genuinely inherits the section's choice, so "Section
                // default" is what is actually happening; radius inherits
                // nothing, so there it would be a lie. Not an oversight.
                Select::make('style_background_color')
                    ->label('Background colour')
                    ->options(fn (): array => self::paletteOptions())
                    ->placeholder('Section default')
                    ->native(false)
                    ->helperText(fn (): string => self::paletteHelp('Fills the '.$thing.' edge to edge. Panels and cards inside keep their own styling.')),

                Select::make('style_text_color')
                    ->label('Text colour')
                    ->options(fn (): array => self::paletteOptions())
                    ->placeholder('Section default')
                    ->native(false)
                    ->helperText(fn (): string => self::paletteHelp('Sets the colour copy inherits. Elements this '.$thing.' styles explicitly keep their own colour.')),

                Select::make('style_accent_color')
                    ->label('Accent colour')
                    ->options(fn (): array => self::paletteOptions())
                    ->placeholder('Section default')
                    ->native(false)
                    ->helperText(fn (): string => self::paletteHelp('Eyebrows, emphasised words and stat figures. Kept separate from the text colour so the accent still stands out against it.')),

                Select::make('style_button_color')
                    ->label('Button colour')
                    ->options(fn (): array => self::paletteOptions())
                    ->placeholder('Section default')
                    ->native(false)
                    ->helperText(fn (): string => self::paletteHelp('Fills this '.$thing.'\'s buttons.'))
                    ->hintIcon(Heroicon::InformationCircle, 'You do not choose the label colour and there is no control for it. It is worked out from the fill you pick — black or white, whichever stays readable — so a button can never come out unreadable. Pick the fill you want and trust the label.'),

                Select::make('style_border_color')
                    ->label('Border colour')
                    ->options(fn (): array => self::paletteOptions())
                    ->placeholder('No border')
                    ->native(false)
                    ->helperText(fn (): string => self::paletteHelp('Draws a border around the '.$thing.'. Pick a width beside it, or the border stays hairline.')),

                Select::make('style_border_width')
                    ->label('Border width')
                    ->options(self::SIZES)
                    ->placeholder('None (default)')
                    ->native(false)
                    // The old text ("Only visible once a border colour is
                    // chosen") read as a promise about THIS CONTROL, which is
                    // always shown. It was describing the border. Kept visible
                    // deliberately rather than gated behind the colour: hiding
                    // it would put a stored width out of reach on any section
                    // that has one and no colour.
                    ->helperText('How thick the border is.')
                    ->hintIcon(Heroicon::InformationCircle, 'A width on its own draws nothing — the border appears only once you pick a border colour beside it. The reverse pairing does have a fallback: choose a colour and leave this unset and you get a hairline.'),

                Select::make('style_radius')
                    // NOT self::SIZES. Every other size scale reads
                    // None/Small/Medium/Large, but "None" for a radius reads as
                    // "no setting" rather than "square corners", which is what
                    // the value actually means — and the helper text used to
                    // tell operators to choose a "Square" option that did not
                    // exist. The STORED VALUE IS STILL `none`: this is a label,
                    // so no data changes and no token is added.
                    ->label('Corner radius')
                    ->options(['none' => 'Square'] + self::SIZES)
                    ->placeholder($nested ? 'Block default' : 'Section default')
                    ->native(false)
                    // The two levels do NOT behave alike here, so the copy must
                    // not either. A section's radius cancels its own full-bleed
                    // and visibly narrows the band (`radius-tokens()` in
                    // _layout-frame.scss); a block has no bleed to cancel —
                    // `.sxb-radius--*` sets `border-radius` and nothing else —
                    // so its width is identical at every token. Telling a block
                    // operator that Square "keeps it full width" would describe
                    // a distinction that does not exist one level down.
                    ->helperText($nested
                        ? 'Rounds this block\'s corners.'
                        : 'Rounds the section into a card, pulling it in from the screen edges so the corners are visible.')
                    ->hintIcon(Heroicon::InformationCircle, $nested
                        ? 'Corners only. Unlike on a section, a radius here does not change the block\'s width — it already sits inside its section\'s column rather than against the screen edge. "Square" simply means no rounding.'
                        : 'A radius stops the section running edge to edge — that is the control working, not a bug. "Square" is an explicit choice that KEEPS it full width, which is not the same as leaving this unset: unset means "use the design default", and a default could round it.'),

                SectionImagePicker::make('style_background_image')
                    ->label('Background image')
                    ->helperText('Sits behind the '.$thing.', covering it. Pair it with a background colour so text stays readable while the image loads.')
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
        return PaletteChoices::options();
    }

    /**
     * An empty palette makes both colour selects look broken, so say where
     * colours come from rather than offering an empty dropdown silently.
     */
    private static function paletteHelp(string $base): string
    {
        return PaletteChoices::help($base);
    }

    /**
     * The size scale shared by every spacing knob.
     *
     * `none` is NOT redundant with leaving the knob unset, and that is the
     * whole reason it exists. A tier holding null means "inherit the width
     * below", never "reset" — so without an explicit zero an operator could
     * add padding on mobile and have no way to take it away again on desktop.
     *
     * @var array<string, string>
     */
    private const SIZES = [
        'none' => 'None',
        'sm' => 'Small',
        'md' => 'Medium',
        'lg' => 'Large',
    ];

    /** @var array<string, string> */
    private const ALIGNMENTS = [
        'left' => 'Left',
        'center' => 'Centre',
        'right' => 'Right',
    ];

    /**
     * Layout knobs every section type gets, stored alongside the
     * type-specific fields in the same `data` payload.
     *
     * Deliberately a fixed vocabulary of sizes rather than free-form pixel
     * values: the frontend maps each to a CSS custom property, so pages stay
     * visually consistent, operators stay out of inline styling, and the
     * scale can be retuned globally without touching content.
     *
     * WHY VERTICAL PADDING LIVES HERE while its keys are named `style_*`:
     * the prefix exists to stop a knob colliding with an authored field in
     * the flat `data` payload (see LayoutFields::KEYS), and "padding" is
     * exactly the sort of word a blueprint would reach for. It says nothing
     * about which panel the control belongs in, and spacing belongs next to
     * the other spacing controls.
     *
     * THERE IS NO LEFT/RIGHT PADDING, deliberately. The horizontal edges are
     * owned by `content_inset`, which acts on the CONTENT COLUMN. A padding
     * knob would act on the knob wrapper instead, narrowing the containing
     * block of the section band — and the band's own bleed is a fixed
     * `-1 * --page-gutter` that recovers the gutter but not the knob, so
     * every self-painting section, the stats marquee and the hero stage would
     * end up inset from the viewport edge by exactly the padding chosen. One
     * pair of horizontal controls, on the box that can actually move safely.
     *
     * @param  bool  $nested  True when this panel is being built for a typed
     *                        child block rather than a top-level section.
     */
    public static function layoutSection(bool $nested = false): Section
    {
        $thing = $nested ? 'block' : 'section';

        return Section::make('Layout & spacing')
            ->description('How this '.($nested ? 'block sits inside its section' : 'section sits on the page').'. Leave everything unset for the design default.')
            ->collapsed()
            ->columns(2)
            ->components([
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
                    ->helperText('Caps how wide the content runs, centred. Leave unset to use the design default.')
                    // Was "the only setting here with no per-screen override",
                    // which is false — media width has none either, and an
                    // operator who opens the tabs sees that immediately.
                    ->hintIcon(Heroicon::InformationCircle, 'This has no per-screen override, and cannot usefully have one: a width cap does nothing on a screen narrower than the cap, so a mobile override could not have an effect. Media width has no override either, by choice rather than by physics.'),

                Select::make('media_width')
                    ->label('Media width')
                    ->options([
                        'contained' => 'Contained — sits inside the content column',
                        'full' => 'Full bleed — spans the section edge to edge',
                    ])
                    ->placeholder('Design default')
                    ->native(false)
                    ->helperText('How this '.$thing.'\'s image or video is framed. Leave unset to use the design default.'),

                self::insetField($nested),

                Select::make('content_align')
                    ->label('Content alignment')
                    ->options(self::ALIGNMENTS)
                    ->placeholder('Design default')
                    ->native(false)
                    ->helperText('Aligns headings, copy and buttons within the '.$thing.'.'),

                self::paddingBox($nested),

                // Overrides only. The controls above are the value at EVERY
                // width; these two tabs change it from a breakpoint upwards,
                // so an unset tab field genuinely means "carry on from the
                // width below" rather than "no padding". Mobile is the base
                // because it is the majority of this site's traffic — and
                // because a phone usually wants LESS of everything, which is
                // easier to express by adding upward than by subtracting.
                Tabs::make('Responsive overrides')
                    ->tabs([
                        self::overrideTab('Tablet up', 'md', '768px', $nested),
                        self::overrideTab('Desktop up', 'lg', '992px', $nested),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Horizontal inset, with the `flush` option only where it means anything.
     *
     * `flush` counter-bleeds `--page-gutter` so content reaches the viewport
     * edge — the one thing the old scale could not express, because its
     * smallest token still clamped to 12px and the section band re-pads the
     * gutter underneath it regardless. A CHILD BLOCK is not adjacent to the
     * page edge; its inset is scoped inside its parent's column, so offering
     * `flush` there would be a control that does nothing. Hence the flag.
     */
    private static function insetField(bool $nested): Select
    {
        $options = [
            'none' => 'None',
            'sm' => 'Small',
            'md' => 'Medium',
            'lg' => 'Large',
            'xl' => 'Extra large',
        ];

        if (! $nested) {
            $options = ['flush' => 'Flush — content touches the screen edge'] + $options;
        }

        return Select::make('content_inset')
            ->label('Horizontal inset')
            ->options($options)
            ->placeholder('None (default)')
            ->native(false)
            ->helperText($nested
                ? 'Pulls this block\'s text and buttons in from its left and right edges.'
                : 'Pulls text and buttons in from the left and right edges — this is the section\'s left/right padding. Background images stay full width.')
            ->hintIcon(Heroicon::InformationCircle, $nested
                // An operator who used `flush` on a section WILL look for it
                // here. Saying why it is absent is cheaper than letting them
                // conclude the form is inconsistent.
                ? 'There is no "Flush" option on a block, unlike on a section. Flush works by cancelling the page margin, and a block sits inside its section\'s column rather than against the screen edge — so the option would have nothing to cancel and is left out rather than shipped doing nothing.'
                : '"Flush" is the one value that SUBTRACTS: it removes the page margin entirely so content runs right to the screen edge. Pair it with a tablet override to keep that on phones only. It is not offered on cards inside a section, which are not next to the screen edge.');
    }

    /**
     * Top and bottom padding as one box row, the shape an operator coming
     * from any other page builder expects.
     *
     * This REPLACES `extra_padding`, which was a single token driving both
     * edges at once — so "generous above, tight below" was unsayable. The old
     * key is retired rather than kept as an alias: it was set on no live row,
     * only on the /test-page bench, which is rewritten in the same change.
     */
    private static function paddingBox(bool $nested = false): Grid
    {
        $thing = $nested ? 'block' : 'section';

        return Grid::make(2)
            ->components([
                Select::make('style_padding_top')
                    ->label('Padding top')
                    ->options(self::SIZES)
                    ->placeholder('None (default)')
                    ->native(false)
                    // The distinction an operator worked out unaided, and the
                    // reason this whole hint pass exists.
                    ->hintIcon(Heroicon::InformationCircle, '"None" is not the same as leaving this empty. Empty means "use this '.$thing.'\'s design default"; None forces zero and overrides that default. They often look identical today, which is exactly why it is worth knowing before one of them changes under you.'),

                Select::make('style_padding_bottom')
                    ->label('Padding bottom')
                    ->options(self::SIZES)
                    ->placeholder('None (default)')
                    ->native(false)
                    // The ADVICE is the same at both levels; the REASON is
                    // not. A section's band bleeds to the viewport edge, so
                    // horizontal padding on the wrapper would strand it inset
                    // by exactly the padding chosen. A block has no bleed and
                    // is nowhere near the screen edge, so that consequence
                    // cannot happen and claiming it would be inventing physics.
                    ->hintIcon(Heroicon::InformationCircle, $nested
                        ? 'There is no left or right padding here, and it is deliberate rather than missing: the horizontal edges belong to "Horizontal inset" above, which is the control that moves them correctly inside the section\'s column.'
                        : 'There is no left or right padding here, and it is deliberate rather than missing: the horizontal edges belong to "Horizontal inset" above. A left/right padding control would act on the outer band instead, and would leave every section that paints its own background pulled in from the screen edge by exactly the amount chosen.'),
            ])
            ->columnSpanFull();
    }

    /**
     * One breakpoint tab: the same four knobs, suffixed.
     *
     * The suffix is flat (`content_align_md`), not nested, because
     * SectionContent::hasContent() skips presentation keys BY NAME — so a
     * suffixed key becomes presentation by being listed in LayoutFields::KEYS
     * and nothing else has to learn the shape. Filament needs no statePath
     * work for the same reason: it is an ordinary sibling key in `data`.
     *
     * Only the knobs that can meaningfully differ by width are here.
     * `content_width` is a max-width CAP and is inert below the cap, so a
     * mobile override of it is a no-op by physics; `media_width` and the
     * colours have no per-width reading an operator would want.
     */
    private static function overrideTab(string $label, string $suffix, string $from, bool $nested): Tabs\Tab
    {
        $placeholder = 'Same as narrower screens';

        $inset = self::insetField($nested);

        return Tabs\Tab::make($label)
            ->badge($from)
            ->columns(2)
            ->components([
                Select::make("content_inset_{$suffix}")
                    ->label('Horizontal inset')
                    ->options($inset->getOptions())
                    ->placeholder($placeholder)
                    ->native(false)
                    // Stated once per tab, on the first control, because Tabs
                    // itself carries no hint or description in Filament 4.
                    ->hintIcon(Heroicon::InformationCircle, 'These override UPWARD. The settings above the tabs are the value at every width — so they are the PHONE value — and this tab changes them from '.$from.' up. Left as "'.$placeholder.'", a field here genuinely inherits; it does not mean "none". To remove something on wider screens that you set on phones, choose "None" explicitly.'),

                Select::make("content_align_{$suffix}")
                    ->label('Content alignment')
                    ->options(self::ALIGNMENTS)
                    ->placeholder($placeholder)
                    ->native(false),

                Select::make("style_padding_top_{$suffix}")
                    ->label('Padding top')
                    ->options(self::SIZES)
                    ->placeholder($placeholder)
                    ->native(false),

                Select::make("style_padding_bottom_{$suffix}")
                    ->label('Padding bottom')
                    ->options(self::SIZES)
                    ->placeholder($placeholder)
                    ->native(false),
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
     * Reusing styleSection()/layoutSection() is deliberate — a knob that means
     * one thing on a section and another on a child would be the "control that
     * lies to the operator" shape this system keeps fixing. It is no longer
     * VERBATIM: `nested: true` withholds the inset's `flush` option, for the
     * reason given at the call below. That is the same principle, not an
     * exception to it — the option is dropped precisely because it could not
     * mean the same thing here.
     */
    public static function blockFor(BlockBlueprint $blueprint): Block
    {
        return Block::make($blueprint->type())
            ->label($blueprint->label())
            ->icon($blueprint->icon())
            ->schema([
                ...$blueprint->formSchema(),
                self::styleSection(nested: true),
                // NESTED. The panels are otherwise identical at both levels on
                // purpose — a knob meaning one thing on a section and another
                // on a child is the "control that lies to the operator" shape
                // this system keeps removing. The single exception is the
                // inset's `flush` value, which is defined against the PAGE
                // gutter: a child sits inside its parent's column and is not
                // adjacent to the screen edge, so the option would do nothing
                // there and is withheld rather than shipped inert.
                self::layoutSection(nested: true),
            ])
            ->columns(2);
    }
}
