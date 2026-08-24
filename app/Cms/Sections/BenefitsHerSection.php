<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class BenefitsHerSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::BenefitsHer;
    }

    public function label(): string
    {
        return 'Benefits — for her';
    }

    public function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public function description(): ?string
    {
        return 'Dark-theme 3-col grid: 2×2 protocol-card grid on the LEFT, pitch column (eyebrow + headline + lead + lifestyle photo + CTA) on the right. Mirror to Benefits-Him.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'lead' => null,
            'image' => null,
            'image_alt' => null,
            'cta_label' => null,
            'cta_url' => null,
            'benefits' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header & pitch')
                ->columns(2)
                ->components([
                    CopyFields::inline('eyebrow')->label('Editorial tag'),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('emphasis')
                        ->label('Heading accent (italic gold)')
                        ->helperText('Optional italic gold run rendered after the heading.'),
                    CopyFields::inline('lead')->columnSpanFull(),
                    TextInput::make('cta_label')->maxLength(60),
                    TextInput::make('cta_url')->maxLength(2048),
                    SectionImagePicker::make('image')->label('Lifestyle image'),
                    TextInput::make('image_alt')->maxLength(255),
                ]),
            Repeater::make('benefits')
                ->label('Protocol cards (4 typical)')
                ->schema([
                    CopyFields::inline('category')->label('Tag (mono uppercase)'),
                    CopyFields::inline('pill')->label('Badge pill'),
                    CopyFields::inline('title')->required(),
                    CopyFields::prose('body')->required()->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['image' => 'image'];
    }
}
