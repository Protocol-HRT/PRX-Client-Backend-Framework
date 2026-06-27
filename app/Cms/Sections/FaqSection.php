<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class FaqSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Faq;
    }

    public function label(): string
    {
        return 'FAQ';
    }

    public function icon(): string
    {
        return 'heroicon-o-question-mark-circle';
    }

    public function description(): ?string
    {
        return 'Light-theme FAQ accordion. Centered max-w-3xl column. Sage section-label eyebrow → display heading with sage emphasis on the last word → bordered disclosure rows. Alpine-driven (one open at a time).';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'Common Questions',
            'heading' => 'Frequently asked',
            'emphasis' => 'questions',
            'faqs' => [
                ['q' => 'Is this legal?',
                 'a' => 'Yes. [Brand Name] is a fully licensed telemedicine platform operating in all 50 states. Every protocol is physician-reviewed and prescribed through a legitimate medical process. We operate under the same regulatory framework as any licensed medical practice.'],
                ['q' => 'Is it safe?',
                 'a' => 'Every protocol is reviewed and approved by a licensed [Brand Name] physician before it reaches you. We only recommend compounds with strong clinical evidence profiles. Our AI concierge is trained on thousands of peer-reviewed studies, and your safety is built into every step of the process.'],
                ['q' => 'How fast does it ship?',
                 'a' => "Once your protocol is physician-approved, your medication ships directly to your door, typically within 3–5 business days. You'll receive tracking information and can reach our AI concierge 24/7 with any questions."],
                ['q' => "What if it doesn't work?",
                 'a' => "We stand behind our protocols with the [Brand Name] Guarantee. If you follow your prescribed protocol and don't see measurable results, we will work with you to adjust your protocol at no additional cost. Over 94% of our patients report measurable results within 90 days."],
                ['q' => 'How do I cancel?',
                 'a' => 'You can cancel anytime. No contracts, no hidden fees, no runaround. Simply contact our team through the AI concierge or by email and your subscription will be cancelled immediately. We believe in earning your loyalty, not locking you in.'],
            ],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    TextInput::make('eyebrow')->label('Sage eyebrow')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent (sage)')
                        ->maxLength(255)
                        ->helperText('Final-clause word(s) rendered in sage green.'),
                ]),
            Repeater::make('faqs')
                ->label('Questions')
                ->schema([
                    TextInput::make('q')->label('Question')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('a')->label('Answer')->rows(4)->required()->columnSpanFull(),
                ])
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['q'] ?? null),
        ];
    }
}
