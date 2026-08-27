<?php

namespace App\Cms\Sections;

use App\Cms\Sections\Concerns\HasCatalogCta;
use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * The lead-magnet ingress: the block that starts the health-goal quiz, and
 * which an operator can drop on any page that should feed the flow.
 *
 * Two things this blueprint deliberately does NOT offer:
 *
 * - **A background field.** Every section already carries
 *   `style_background_color` and `style_background_image` in the Style panel.
 *   A `background_image` content field here would be a second control for one
 *   surface, which is the collision the layout knobs were namespaced `style_*`
 *   to end — it hid those sections' images from `has_content` and painted the
 *   band twice.
 * - **A fixed heading level.** This section is built to lead a page but can
 *   also sit mid-page above a stack listing, and a page with two H1s reads as
 *   two documents. The operator picks, with the reason in the help text.
 *
 * The icon fields are the `svg` kind rather than an image: these render at
 * 16–18px inside a pill and a button, where a raster icon cannot inherit the
 * text colour and goes muddy against an operator-chosen background.
 * `SectionDataTransformer` runs `SvgSanitizer` over that kind on the way out,
 * which is the only reason the frontend injects it.
 */
class QuizCtaSection extends SectionBlueprint
{
    use HasCatalogCta;

    public function type(): SectionType
    {
        return SectionType::QuizCta;
    }

    public function label(): string
    {
        return 'Quiz CTA (lead magnet)';
    }

    public function icon(): string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public function description(): ?string
    {
        return 'Ingress to the health-goal quiz: eyebrow, headline, short pitch, a CTA with an icon and a reassurance line, and trust badges.';
    }

    public function defaults(): array
    {
        return array_merge($this->ctaDefaults(), [
            'eyebrow' => null,
            'heading' => null,
            'heading_level' => 'h2',
            'body' => null,
            'cta_icon' => null,
            'cta_subtext' => null,
            'badges' => [],
            'media' => null,
            'media_caption' => null,
        ]);
    }

    public function formSchema(): array
    {
        return [
            Section::make('Copy')
                ->components([
                    CopyFields::inline('eyebrow')
                        ->helperText('Small line above the headline, shown with a dot marker. "Your free peptide report".'),
                    CopyFields::inline('heading')
                        ->required()
                        ->helperText('Wrap the words you want picked out in italics — they render in the accent colour, not as italic type.'),
                    Select::make('heading_level')
                        ->label('Headline level')
                        ->options([
                            'h1' => 'H1 — this section is the page heading',
                            'h2' => 'H2 — the page has its own heading',
                        ])
                        ->default('h2')
                        ->native(false)
                        ->helperText('Use H1 only when this is the top of the page. Two H1s read as two documents to a search engine.'),
                    CopyFields::prose('body')
                        ->label('Pitch')
                        ->helperText('One or two sentences. Bold the part that names what they get.'),
                ]),

            Section::make('Call to action')
                ->columns(2)
                ->components([
                    ...$this->ctaFields(),
                    Textarea::make('cta_icon')
                        ->label('Button icon (SVG)')
                        ->rows(3)
                        ->helperText('Optional. Paste SVG markup. Use stroke="currentColor" so it follows the button text colour.')
                        ->columnSpanFull(),
                    CopyFields::inline('cta_subtext')
                        ->label('Under the button')
                        ->helperText('The reassurance line. "Takes 2 minutes · No payment".'),
                ]),

            Section::make('Trust badges')
                ->description('Short proof points shown as pills under the button. Leave empty to hide the row.')
                ->components([
                    Repeater::make('badges')
                        ->label('Badges')
                        ->addActionLabel('Add badge')
                        ->columns(2)
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->schema([
                            CopyFields::inline('label')
                                ->required()
                                ->helperText('Three or four words.'),
                            Textarea::make('icon')
                                ->label('Icon (SVG)')
                                ->rows(3)
                                ->helperText('Optional. stroke="currentColor" keeps it in step with the text.'),
                        ]),
                ]),

            Section::make('Side image')
                ->description('Optional. Without it the section renders as a single centred column.')
                ->columns(2)
                ->components([
                    SectionImagePicker::make('media')->label('Image'),
                    CopyFields::inline('media_caption')
                        ->label('Image seal')
                        ->helperText('Two or three words stamped on the image. Leave empty for none.'),
                ]),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return [
            'media' => 'image',
            'cta_icon' => 'svg',
            'badges.*.icon' => 'svg',
        ];
    }

    public function resolveData(array $data): array
    {
        return $this->resolveCta($data);
    }
}
