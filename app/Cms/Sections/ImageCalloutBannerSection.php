<?php

namespace App\Cms\Sections;

use App\Cms\Sections\Concerns\HasCatalogCta;
use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class ImageCalloutBannerSection extends SectionBlueprint
{
    use HasCatalogCta;

    public function type(): SectionType
    {
        return SectionType::ImageCalloutBanner;
    }

    public function label(): string
    {
        return 'Image callout banner';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function description(): ?string
    {
        return 'Full-width background image with up to two floating callouts (icon/logo, title, text, optional CTA), positioned left and/or right. Each callout renders as a frosted card or as plain feature copy; the photo can be monochrome-tinted.';
    }

    public function defaults(): array
    {
        return [
            'background_image' => null,
            'background_alt' => null,
            'background_treatment' => 'none',
            'tint_color' => null,
            'callouts' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Background')
                ->columns(2)
                ->components([
                    SectionImagePicker::make('background_image')->label('Background image'),
                    TextInput::make('background_alt')
                        ->label('Background alt text')
                        ->maxLength(255),
                    Select::make('background_treatment')
                        ->options(['none' => 'None', 'tint' => 'Monochrome tint'])
                        ->default('none')
                        ->native(false)
                        ->live()
                        ->helperText('Monochrome tint recolors the photo to a single hue (mix-blend color).'),
                    ColorPicker::make('tint_color')
                        ->label('Tint color')
                        ->visible(fn (callable $get): bool => $get('background_treatment') === 'tint')
                        ->helperText('Leave empty for the warm neutral default.'),
                ]),
            Repeater::make('callouts')
                ->label('Callout cards')
                ->maxItems(2)
                ->schema([
                    Select::make('position')
                        ->options(['0' => 'Position 1 (left)', '1' => 'Position 2 (right)'])
                        ->default('0')
                        ->required()
                        ->native(false)
                        ->helperText('Which slot over the image the card occupies.'),
                    Select::make('variant')
                        ->options([
                            'card' => 'Frosted card',
                            'media-card' => 'Compact media card',
                            'feature' => 'Feature copy (no card)',
                        ])
                        ->default('card')
                        ->native(false)
                        ->helperText('Compact media card: small left-aligned frosted card (image over text). Feature copy: large serif title with left-aligned text directly over the image, no card.'),
                    Select::make('align')
                        ->options(['center' => 'Vertically centered', 'top' => 'Top', 'bottom' => 'Bottom'])
                        ->default('center')
                        ->native(false)
                        ->helperText('Vertical placement inside the banner on desktop.'),
                    ColorPicker::make('color')
                        ->label('Card color')
                        ->helperText('Card background. Leave empty for the frosted light default.'),
                    SectionImagePicker::make('icon')->label('Icon / logo'),
                    TextInput::make('icon_width')
                        ->label('Icon width (px)')
                        ->numeric()
                        ->minValue(16)
                        ->maxValue(600)
                        ->helperText('Rendered width of the icon/logo. Leave empty for the small default.'),
                    CopyFields::inline('title'),
                    CopyFields::prose('content')
                        ->columnSpanFull()
                        ->helperText('Rendered as HTML on the public site — bold/italic/line breaks are honored.'),
                    ...$this->ctaFields(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
        ];
    }

    public function fieldKinds(): array
    {
        return [
            'background_image' => 'image',
            'callouts.*.icon' => 'image',
        ];
    }

    public function resolveData(array $data): array
    {
        $data['callouts'] = array_map(
            function (array $callout): array {
                // Filament re-saves select values as integers; the API
                // contract for slot positions is string '0' / '1'.
                if (isset($callout['position'])) {
                    $callout['position'] = (string) $callout['position'];
                }

                return $this->resolveCta($callout);
            },
            array_values($data['callouts'] ?? []),
        );

        return $data;
    }
}
