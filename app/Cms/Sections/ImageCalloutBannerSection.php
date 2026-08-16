<?php

namespace App\Cms\Sections;

use App\Cms\Sections\Concerns\HasCatalogCta;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
        return 'Full-width background image with up to two floating callout cards (icon/logo, title, text, optional CTA), positioned left and/or right.';
    }

    public function defaults(): array
    {
        return [
            'background_image' => null,
            'background_alt' => null,
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
                    ColorPicker::make('color')
                        ->label('Card color')
                        ->helperText('Card background. Leave empty for the frosted light default.'),
                    SectionImagePicker::make('icon')->label('Icon / logo'),
                    TextInput::make('title')->maxLength(255),
                    Textarea::make('content')->rows(4)->maxLength(2000)->columnSpanFull(),
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
            fn (array $callout): array => $this->resolveCta($callout),
            array_values($data['callouts'] ?? []),
        );

        return $data;
    }
}
