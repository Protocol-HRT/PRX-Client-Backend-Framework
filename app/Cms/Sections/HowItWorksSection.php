<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class HowItWorksSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::HowItWorks;
    }

    public function label(): string
    {
        return 'How it works (process)';
    }

    public function icon(): string
    {
        return 'heroicon-o-list-bullet';
    }

    public function description(): ?string
    {
        return 'Light-theme process explanation. 2-col header (heading left, lead + dark CTA right), then a 3-col step grid with circular step-numbers and connector lines.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'The Process',
            'heading' => 'Begin your hormone optimization',
            'emphasis' => 'journey.',
            'lead' => 'Three simple steps between you and the protocol that changes everything.',
            'cta_label' => 'Get Started',
            'cta_url' => '#pricing',
            'steps' => [
                [
                    'number' => '01',
                    'title' => 'Consult',
                    'meta' => 'Takes about 5 minutes',
                    'body' => 'Complete a quick online evaluation. Our AI concierge, trained on thousands of peer-reviewed clinical studies, listens, learns, and begins building your personalized profile.',
                ],
                [
                    'number' => '02',
                    'title' => 'Your Protocol',
                    'meta' => 'Physician-reviewed · All 50 states',
                    'body' => 'Our AI cross-references your profile against our clinical database to build your protocol. A licensed [Brand Name] physician reviews and approves every recommendation before it reaches you.',
                ],
                [
                    'number' => '03',
                    'title' => 'Delivered',
                    'meta' => 'Ongoing AI support 24/7',
                    'body' => 'Your medication ships directly to your door. Check in with our AI concierge anytime. Your protocol evolves as your body responds, with ongoing support 24/7.',
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
                    TextInput::make('eyebrow')->label('Eyebrow (sage)')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent (sage)')
                        ->maxLength(255)
                        ->helperText('Sage run rendered after the heading.'),
                    Textarea::make('lead')->rows(3)->maxLength(500)->columnSpanFull(),
                    TextInput::make('cta_label')->maxLength(60),
                    TextInput::make('cta_url')->maxLength(2048),
                ]),
            Repeater::make('steps')
                ->label('Steps')
                ->schema([
                    TextInput::make('number')
                        ->required()
                        ->maxLength(8)
                        ->helperText('e.g. 01, 02, 03 — rendered in the circular badge.'),
                    TextInput::make('title')->required()->maxLength(255),
                    TextInput::make('meta')
                        ->label('Sub label (sage mono)')
                        ->maxLength(255)
                        ->helperText('Tiny line above the title (e.g. "Takes about 5 minutes").'),
                    Textarea::make('body')->rows(4)->required()->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => isset($state['number'], $state['title']) ? "{$state['number']} · {$state['title']}" : null),
        ];
    }
}
