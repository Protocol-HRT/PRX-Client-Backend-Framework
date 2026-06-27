<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class TestimonialsSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Testimonials;
    }

    public function label(): string
    {
        return 'Testimonials';
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function description(): ?string
    {
        return 'Dark-theme testimonials grid. 2-col header (heading left, optional rating-stats row right), then a 2-col grid of dark cards: 5 gold stars + protocol tag + italic quote + avatar/name/verified-checkmark footer.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'Real Results',
            'heading' => 'Thousands have',
            'emphasis' => 'transformed.',
            'rating_stats' => [
                ['value' => '10,000+', 'label' => 'Patients'],
                ['value' => '4.9/5',   'label' => 'Average Rating'],
                ['value' => '94%',     'label' => 'Report Measurable Results'],
            ],
            'quotes' => [
                [
                    'name' => 'Marcus T., 47',
                    'title' => 'Executive & Former Athlete',
                    'protocol' => 'HORMONE OPTIMIZATION',
                    'quote' => "I've tried every supplement stack imaginable. [Brand Name]'s physician-reviewed protocol genuinely changed my life. My testosterone is optimized, my recovery is faster, and I feel 15 years younger.",
                    'stars' => 5,
                    'initials' => 'MT',
                    'image' => null,
                ],
                [
                    'name' => 'Sarah K., 38',
                    'title' => 'Competitive CrossFit Athlete',
                    'protocol' => 'BODY RECOMPOSITION',
                    'quote' => "As a woman, I was skeptical this was for me. The AI and medical team understood my goals completely: body recomposition, performance, and hormonal balance. The protocol was unlike anything I'd seen.",
                    'stars' => 5,
                    'initials' => 'SK',
                    'image' => null,
                ],
                [
                    'name' => 'David R., 52',
                    'title' => 'Biohacker & Entrepreneur',
                    'protocol' => 'LONGEVITY & NOOTROPICS',
                    'quote' => 'The depth of clinical knowledge behind this platform is staggering. Real studies, clear explanations, a protocol I could actually verify. This is the future of personalized medicine.',
                    'stars' => 5,
                    'initials' => 'DR',
                    'image' => null,
                ],
                [
                    'name' => 'Dr. [First Name] [Last Name]',
                    'title' => 'Chief Medical Officer · ER Physician',
                    'protocol' => 'PHYSICIAN ENDORSEMENT',
                    'quote' => 'The clinical foundation of [Brand Name] is unlike anything else in telemedicine. Decades of frontline medicine and deep mastery of hormone and peptide science are built into every protocol.',
                    'stars' => 5,
                    'initials' => 'JP',
                    'image' => '/images/physicians/physician-2-thumb.webp',
                ],
                [
                    'name' => 'Dr. [First Name] [Last Name]',
                    'title' => 'Founding Physician · Lifestyle Management Expert',
                    'protocol' => 'PHYSICIAN ENDORSEMENT',
                    'quote' => '[Brand Name] was born from a relentless pursuit of what the body is actually capable of when given the right tools: the right hormones, the right peptides, the right protocol.',
                    'stars' => 5,
                    'initials' => 'BB',
                    'image' => '/images/physicians/physician-1-thumb.webp',
                ],
                [
                    'name' => 'Jennifer M., 44',
                    'title' => 'Physician',
                    'protocol' => "WOMEN'S HORMONE PROTOCOL",
                    'quote' => 'As a doctor, I was impressed by the clinical rigor. Real studies, explained contraindications, a protocol I could verify. Remarkable technology backed by remarkable physicians.',
                    'stars' => 5,
                    'initials' => 'JM',
                    'image' => null,
                ],
                [
                    'name' => 'Ashley R., NP',
                    'title' => "Nurse Practitioner · Women's Health",
                    'protocol' => "WOMEN'S HORMONE OPTIMIZATION",
                    'quote' => 'What fills my heart every single day is hearing my patients say their lives have completely changed. When a woman finally feels like herself again — her energy, her confidence, her joy — because we optimized her hormones, there is nothing more rewarding.',
                    'stars' => 5,
                    'initials' => 'AR',
                    'image' => '/images/physicians/provider-3-thumb.webp',
                ],
            ],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->columns(2)
                ->components([
                    TextInput::make('eyebrow')->label('Editorial tag')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent (italic gold)')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            Repeater::make('rating_stats')
                ->label('Rating stats (right of header — leave empty to hide)')
                ->schema([
                    TextInput::make('value')->required()->maxLength(60),
                    TextInput::make('label')->required()->maxLength(120),
                ])
                ->columns(2)
                ->reorderable()
                ->maxItems(4)
                ->columnSpanFull(),
            Repeater::make('quotes')
                ->label('Testimonials')
                ->schema([
                    TextInput::make('protocol')
                        ->label('Protocol tag (mono uppercase)')
                        ->maxLength(120)
                        ->helperText('e.g. "HORMONE OPTIMIZATION", "PHYSICIAN ENDORSEMENT".'),
                    TextInput::make('stars')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(5)
                        ->default(5)
                        ->helperText('0–5 gold stars rendered above the quote.'),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('title')->label('Title / role')->maxLength(255),
                    TextInput::make('image')->label('Headshot path')->maxLength(2048),
                    TextInput::make('initials')
                        ->maxLength(4)
                        ->helperText('Fallback when no image is set.'),
                    Textarea::make('quote')->rows(4)->required()->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
        ];
    }
}
