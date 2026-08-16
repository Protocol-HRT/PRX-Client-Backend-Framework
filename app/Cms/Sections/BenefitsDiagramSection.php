<?php

namespace App\Cms\Sections;

use App\Cms\Sections\Concerns\HasCatalogCta;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class BenefitsDiagramSection extends SectionBlueprint
{
    use HasCatalogCta;

    public function type(): SectionType
    {
        return SectionType::BenefitsDiagram;
    }

    public function label(): string
    {
        return 'Benefits diagram';
    }

    public function icon(): string
    {
        return 'heroicon-o-viewfinder-circle';
    }

    public function description(): ?string
    {
        return 'Centered image with benefit points around it, connected by markers and dashed lines. Optional rating row and CTA (link or add-to-cart) underneath.';
    }

    public function defaults(): array
    {
        return [
            'heading' => null,
            'image' => null,
            'image_alt' => null,
            'marker_style' => 'dot',
            'points' => [],
            'rating_value' => null,
            'rating_text' => null,
            ...$this->ctaDefaults(),
            'cta_subtext' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header & centerpiece')
                ->columns(2)
                ->components([
                    TextInput::make('heading')->maxLength(255)->columnSpanFull(),
                    SectionImagePicker::make('image')->label('Center image'),
                    TextInput::make('image_alt')->maxLength(255),
                    Select::make('marker_style')
                        ->options(['dot' => 'Dot', 'number' => 'Number', 'icon' => 'Per-point icon'])
                        ->default('dot')
                        ->native(false)
                        ->helperText('How each point is marked next to its connector line.'),
                ]),
            Repeater::make('points')
                ->label('Benefit points')
                ->schema([
                    Textarea::make('text')->rows(2)->required()->maxLength(255),
                    Select::make('side')
                        ->options(['left' => 'Left of image', 'right' => 'Right of image'])
                        ->default('left')
                        ->native(false),
                    SectionImagePicker::make('icon')
                        ->label('Marker icon')
                        ->helperText('Only used when marker style is "Per-point icon".'),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['text'] ?? null),
            Section::make('Rating row')
                ->description('Optional. Leave empty to hide the stars line.')
                ->columns(2)
                ->components([
                    TextInput::make('rating_value')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(5)
                        ->step(0.1)
                        ->helperText('Star fill, 0–5.'),
                    TextInput::make('rating_text')
                        ->maxLength(255)
                        ->helperText('Line rendered next to the stars.'),
                ]),
            Section::make('Call to action')
                ->columns(2)
                ->components([
                    ...$this->ctaFields(),
                    TextInput::make('cta_subtext')
                        ->label('CTA subtext')
                        ->maxLength(160)
                        ->helperText('Small reassurance line under the button — renders with a lock icon.'),
                ]),
        ];
    }

    public function fieldKinds(): array
    {
        return [
            'image' => 'image',
            'points.*.icon' => 'image',
        ];
    }

    public function resolveData(array $data): array
    {
        return $this->resolveCta($data);
    }
}
