<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
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
            'eyebrow' => 'For Her',
            'heading' => 'Optimized for',
            'emphasis' => 'her biology.',
            'lead' => "Women's hormones are complex, dynamic, and deeply personal. Our medical team builds protocols that honor the distinct physiology of the female body. No guesswork. No one-size-fits-all.",
            'image' => null,
            'image_alt' => 'Strikingly beautiful feminine woman with glowing skin in elegant silk dress, soft golden light, luxury setting, representing the radiant results of female hormone optimization',
            'cta_label' => 'Build My HER Protocol',
            'cta_url' => '#pricing',
            'benefits' => [
                ['category' => 'HORMONES',  'pill' => 'Foundation', 'title' => 'Hormone Balance',
                    'body' => 'Estrogen, progesterone, and testosterone work together in women too. Our bioidentical hormone protocols are precision-designed around your labs, your symptoms, and your goals.'],
                ['category' => 'METABOLIC', 'pill' => 'Transform',  'title' => 'Weight Loss & Metabolism',
                    'body' => 'GLP-1 support, metabolic optimization, and body recomposition protocols built for female physiology. Clinically proven, physician-reviewed, and personalized to your biology.'],
                ['category' => 'LONGEVITY', 'pill' => 'Rejuvenate', 'title' => 'Energy & Anti-Aging',
                    'body' => 'Combat fatigue, brain fog, and accelerated aging with peptide and longevity protocols that target the root cause, declining hormones, not the symptoms.'],
                ['category' => 'VITALITY',  'pill' => 'Restore',    'title' => "Women's Sexual Health",
                    'body' => 'Restore drive, sensitivity, and balance with targeted protocols addressing female hormonal health at every life stage: perimenopause, menopause, and beyond.'],
            ],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header & pitch')
                ->columns(2)
                ->components([
                    TextInput::make('eyebrow')->label('Editorial tag')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent (italic gold)')
                        ->maxLength(255)
                        ->helperText('Optional italic gold run rendered after the heading.'),
                    Textarea::make('lead')->rows(3)->maxLength(500)->columnSpanFull(),
                    TextInput::make('cta_label')->maxLength(60),
                    TextInput::make('cta_url')->maxLength(2048),
                    SectionImagePicker::make('image')->label('Lifestyle image'),
                    TextInput::make('image_alt')->maxLength(255),
                ]),
            Repeater::make('benefits')
                ->label('Protocol cards (4 typical)')
                ->schema([
                    TextInput::make('category')->label('Tag (mono uppercase)')->maxLength(120),
                    TextInput::make('pill')->label('Badge pill')->maxLength(60),
                    TextInput::make('title')->required()->maxLength(255),
                    Textarea::make('body')->rows(4)->required()->columnSpanFull(),
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
