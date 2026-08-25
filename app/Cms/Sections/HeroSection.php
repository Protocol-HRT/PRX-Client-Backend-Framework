<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Cms\Support\SectionChildren;
use App\Cms\Support\SectionContent;
use App\Enums\SectionType;
use App\Filament\Support\SectionFormBuilder;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class HeroSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Hero;
    }

    public function label(): string
    {
        return 'Hero';
    }

    public function icon(): string
    {
        return 'heroicon-o-star';
    }

    public function description(): ?string
    {
        return 'Full-width hero slideshow: one or more slides (background image + heading + description + CTA each), with an optional floating highlight card. With no slides, the static headline fields render over the background image or video. The banner layout instead renders the static fields as a centered rounded banner (eyebrow, headline, subtext, CTA).';
    }

    public function defaults(): array
    {
        return [
            'layout' => 'slider',
            'slides' => [],
            // WHERE the highlight cards sit on the slideshow — a 9-token
            // anchor, and the ONLY non-null default among the content fields.
            //
            // That non-null-ness is load-bearing, not incidental. It is a
            // presentation key, and DeclaresPresentationKeys classifies any
            // field whose default is non-null as presentation automatically —
            // so this needs NO entry in LayoutFields::KEYS, adds nothing to the
            // shared knob vocabulary, and cannot make a hero carrying only a
            // position report has_content: true. Setting it to null would
            // silently reclassify it as authored copy and let an empty hero
            // reach a live page.
            //
            // A BLUEPRINT FIELD RATHER THAN A SHARED KNOB, deliberately.
            // Positioning a child means something only where the parent owns a
            // slot to position it IN, and the hero is the only section that
            // does. A shared knob would have to be offered on all 24 types and
            // do nothing on 23 of them.
            //
            // `middle-right` is today's hardcoded placement, so an existing
            // hero renders identically until someone changes it.
            'highlight_position' => 'middle-right',
            // `children` is deliberately NOT stamped here. The Builder
            // creates the key on first save, the transformer tolerates its
            // absence, and a seeded flexible MIRROR of this type genuinely
            // cannot hold typed blocks (FlexibleDefinition fans one shared
            // child schema across `.*.`), so declaring it would make the
            // shadow row claim a capability it does not have.
            'highlight_title' => null,
            'highlight_subtitle' => null,
            'highlight_quote' => null,
            'highlight_image' => null,
            'eyebrow' => null,
            'headline' => null,
            'headline_emphasis' => null,
            'subhead' => null,
            'primary_cta_label' => null,
            'primary_cta_url' => null,
            'secondary_cta_label' => null,
            'secondary_cta_url' => null,
            'trust_microcopy' => null,
            'background_image' => null,
            'background_video_url' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Select::make('layout')
                ->options([
                    'slider' => 'Slideshow (slides below)',
                    'banner' => 'Centered banner (static fields below)',
                ])
                ->default('slider')
                ->native(false)
                ->helperText('Centered banner ignores the slides and renders the static hero fields centered over the background image in a rounded frame.'),
            Repeater::make('slides')
                ->label('Slides')
                ->schema([
                    SectionImagePicker::make('image')->label('Background image'),
                    TextInput::make('image_alt')->label('Image alt text')->maxLength(255),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('heading_emphasis')
                        ->label('Heading accent (italic)')
                        ->helperText('Optional accent run rendered after the heading.'),
                    CopyFields::prose('description')
                        ->columnSpanFull()
                        ->helperText('Rendered as HTML on the public site — bold/italic/line breaks are honored.'),
                    TextInput::make('cta_label')->label('CTA label')->maxLength(60),
                    TextInput::make('cta_url')->label('CTA URL')->maxLength(2048),
                    Select::make('text_theme')
                        ->label('Text tone')
                        ->options(['dark' => 'Dark text (light image)', 'light' => 'Light text (dark image)'])
                        ->default('dark')
                        ->native(false)
                        ->helperText('Pick the tone that stays readable over this slide\'s image.'),
                ])
                ->columns(2)
                ->reorderable()
                ->minItems(0)
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['heading'] ?? null),
            SectionFormBuilder::children(['testimonial'])
                ->label('Highlight cards')
                ->helperText('Cards overlaid on the slideshow. Add more than one and they become a mini slider. Each card carries its own style and layout settings.'),
            Select::make('highlight_position')
                ->label('Highlight card position')
                ->options([
                    'top-left' => 'Top left',
                    'top-center' => 'Top centre',
                    'top-right' => 'Top right',
                    'middle-left' => 'Middle left',
                    'middle-center' => 'Middle centre',
                    'middle-right' => 'Middle right',
                    'bottom-left' => 'Bottom left',
                    'bottom-center' => 'Bottom centre',
                    'bottom-right' => 'Bottom right',
                ])
                ->default('middle-right')
                ->selectablePlaceholder(false)
                ->native(false)
                ->helperText('Where the cards above sit on the slideshow. Hidden on phones, where the cards stack under the slide instead — there is not enough width to overlay them without covering the headline.'),
            Section::make('Highlight card (legacy single card)')
                ->description('The original one-card fields, kept so nothing authored before highlight cards existed had to be migrated. They are used ONLY when no highlight cards are added above; adding one supersedes them. Re-author here in the blocks above and these can be cleared.')
                ->collapsed()
                ->columns(2)
                ->components([
                    CopyFields::inline('highlight_title')->label('Title'),
                    CopyFields::inline('highlight_subtitle')->label('Subtitle'),
                    CopyFields::inline('highlight_quote')->label('Quote')->columnSpanFull(),
                    SectionImagePicker::make('highlight_image')->label('Image'),
                ]),
            Section::make('Static hero (no-slides fallback)')
                ->description('Rendered only when no slides are defined: headline + CTAs over the background image or video below.')
                ->collapsed()
                ->columns(2)
                ->components([
                    CopyFields::inline('eyebrow')
                        ->label('Eyebrow tag')
                        ->helperText('Small uppercase pill above the headline.')
                        ->columnSpanFull(),
                    CopyFields::inline('headline')->columnSpanFull(),
                    CopyFields::inline('headline_emphasis')
                        ->label('Headline accent (italic gold)')
                        ->helperText('Optional gold italic run rendered after the headline.')
                        ->columnSpanFull(),
                    CopyFields::inline('subhead')->columnSpanFull(),
                    TextInput::make('primary_cta_label')->maxLength(60),
                    TextInput::make('primary_cta_url')->maxLength(2048),
                    TextInput::make('secondary_cta_label')->maxLength(60),
                    TextInput::make('secondary_cta_url')->maxLength(2048),
                    CopyFields::inline('trust_microcopy')
                        ->label('Trust micro-copy under buttons')
                        ->columnSpanFull(),
                    SectionImagePicker::make('background_image')->label('Background image'),
                    TextInput::make('background_video_url')
                        ->label('Background video embed URL')
                        ->url()
                        ->maxLength(2048)
                        ->helperText('Optional Vimeo/YouTube embed URL with autoplay/loop/mute parameters.'),
                ]),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return [
            'slides.*.image' => 'image',
            'highlight_image' => 'image',
            'background_image' => 'image',
        ];
    }

    /**
     * Read the pre-blocks highlight card as a one-item sub-block.
     *
     * Why serve-time rather than a data migration: exactly one row in this
     * install held highlight_* content, and synthesizing here keeps the
     * frontend contract uniform (children are always the shape) while
     * touching no data. It runs BEFORE has_content is computed
     * (SectionDataTransformer), so the flag stays correct, and before the
     * child pipeline, so a synthesized card gets the same knobs and verdict
     * an authored one gets.
     *
     * highlight_image has already been resolved to a media object by the
     * time this runs; MediaResolver::resolve() is idempotent so passing it
     * through the child pipeline preserves it.
     *
     * Authored children WIN — that is what makes this a migration path and
     * not a permanent second source of truth. Once an operator re-authors
     * the card above, the flat fields can be cleared and this becomes dead.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveData(array $data): array
    {
        if (SectionChildren::items($data) !== []) {
            return $data;
        }

        $card = [
            'title' => $data['highlight_title'] ?? null,
            'subtitle' => $data['highlight_subtitle'] ?? null,
            'quote' => $data['highlight_quote'] ?? null,
            'image' => $data['highlight_image'] ?? null,
        ];

        // Same emptiness rule the rest of the CMS uses, rather than a second
        // hand-rolled one that could disagree with it.
        if (! SectionContent::hasContent($card, [])) {
            return $data;
        }

        $data[SectionChildren::KEY] = [[
            'type' => 'testimonial',
            'data' => $card,
        ]];

        return $data;
    }
}
