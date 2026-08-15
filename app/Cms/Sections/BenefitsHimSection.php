<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class BenefitsHimSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::BenefitsHim;
    }

    public function label(): string
    {
        return 'Benefits — for him';
    }

    public function icon(): string
    {
        return 'heroicon-o-bolt';
    }

    public function description(): ?string
    {
        return 'Dark-theme 3-col grid: pitch column (eyebrow + headline + lead + lifestyle photo + CTA) on the LEFT, 2×2 protocol-card grid on the right. Mirror to Benefits-Her.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'For Him',
            'heading' => 'Reclaim your',
            'emphasis' => 'edge.',
            'lead' => 'Built for men who refuse to accept fatigue, decline, and mediocrity as inevitable. Your hormones drive everything: your strength, drive, clarity, confidence. We put you back in control.',
            'image' => null,
            'image_alt' => 'Lean, athletic male physique — the result of testosterone and hormone optimization',
            'cta_label' => 'Build My HIM Protocol',
            'cta_url' => '#pricing',
            'benefits' => [
                ['category' => 'HORMONES',    'pill' => 'Most Popular',   'title' => 'Testosterone Optimization',
                    'body' => "Low-T isn't just about libido. It affects your muscle mass, mental clarity, mood, and drive. Our TRT protocols are physician-designed, lab-verified, and built around your bloodwork, not a generic dose."],
                ['category' => 'PERFORMANCE', 'pill' => 'Elite Protocol', 'title' => 'Peptide Therapy',
                    'body' => 'BPC-157, TB-500, CJC-1295, Ipamorelin: precision peptide stacks for accelerated recovery, lean muscle growth, and cellular regeneration. The same compounds elite athletes have used for years.'],
                ['category' => 'METABOLIC',   'pill' => 'Transform',      'title' => 'Body Recomposition',
                    'body' => 'Lose fat. Build muscle. Optimize metabolic function. Our recomposition protocols combine hormone optimization with evidence-based metabolic compounds for measurable body transformation.'],
                ['category' => 'VITALITY',    'pill' => 'Restore',        'title' => 'Sexual Health & Vitality',
                    'body' => 'Restore libido, performance, and confidence with targeted protocols addressing the root hormonal causes, not surface-level symptoms.'],
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
