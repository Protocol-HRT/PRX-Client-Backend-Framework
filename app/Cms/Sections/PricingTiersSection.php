<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class PricingTiersSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::PricingTiers;
    }

    public function label(): string
    {
        return 'Pricing tiers';
    }

    public function icon(): string
    {
        return 'heroicon-o-banknotes';
    }

    public function description(): ?string
    {
        return 'Two-card primary pricing grid (Blueprint + TRT-style featured) with an optional full-width peptide / waitlist card below.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'Choose Your Starting Point',
            'main_tiers' => [
                [
                    'pill' => 'FRONT DOOR · ALL PATIENTS',
                    'pill_emoji' => null,
                    'accent' => 'sage',
                    'title' => 'Protocol Blueprint Assessment',
                    'subtitle' => 'AI concierge intake · evidence-based protocol · physician review',
                    'price' => '$49',
                    'price_suffix' => 'one-time',
                    'price_note_micro' => null,
                    'lto_banner' => null,
                    'callout_heading' => '$49 credited in full at checkout',
                    'callout_body' => 'Apply your $49 toward any peptide or peptide combination. Your protocol is effectively free the moment you add a peptide to your order.',
                    'features' => [
                        'Full AI concierge intake and personalized protocol build',
                        'Licensed physician review — async (peptides) or live video call (hormones)',
                        'Diet, sleep, exercise, and supplement stack delivered',
                        'Blood work recommendation if clinically indicated',
                        'No compounds included — protocol and physician review only',
                    ],
                    'route' => "Peptide-only protocols → async physician review, no live call required\nHormone protocols → live physician video call required",
                    'cta_label' => 'Get My Protocol — $49',
                    'cta_url' => '#concierge',
                    'cta_micro' => null,
                    'secondary_label' => "See what's included →",
                    'secondary_url' => '#how-it-works',
                    'guarantees' => [],
                ],
                [
                    'pill' => 'Most Popular',
                    'pill_emoji' => '⭐',
                    'accent' => 'gold',
                    'title' => 'Testosterone Replacement Therapy',
                    'subtitle' => 'All-in monthly program · medication included · live physician video call',
                    'price' => '$149',
                    'price_suffix' => '/month',
                    'price_note_micro' => 'All-in · medication included · limited time pricing',
                    'lto_banner' => '⏳ Launch pricing closes without notice — patients who enroll now are grandfathered at $149/mo for life.',
                    'callout_heading' => 'Lock in $149/mo now',
                    'callout_body' => 'This is a special launch offer. Patients who enroll now are grandfathered at $149/mo for the life of their subscription. Price will increase — lock in before it does.',
                    'features' => [
                        'Testosterone medication included — all-in monthly pricing',
                        'Live physician video call required before prescribing',
                        'Full AI protocol build delivered before your physician visit',
                        'Blood work kit included if clinically indicated',
                        'Monthly refill — delivered to your door',
                        'Stack up to 3 peptides at checkout — async physician approvals, no additional call needed',
                    ],
                    'route' => 'Route: AI intake → Checkout → Live physician video call → Lab kit if indicated → Rx + ship',
                    'cta_label' => 'Start TRT — $149/mo (Lock In Now)',
                    'cta_url' => '#concierge',
                    'cta_micro' => 'Limited time · price increases at launch close',
                    'secondary_label' => null,
                    'secondary_url' => null,
                    'guarantees' => [
                        'Licensed physicians in all 50 states',
                        'Medication shipped to your door',
                        'Grandfathered rate — cancel anytime',
                    ],
                ],
            ],
            'peptide_card' => [
                'enabled' => true,
                'eyebrow_main' => 'PEPTIDE PERFORMANCE · ASYNC APPROVAL · NO LIVE CALL',
                'eyebrow_secondary' => '🧪 FORMULARY PENDING — JOIN WAITLIST',
                'title' => 'Peptide-Only Protocol Stack',
                'subtitle' => 'Select 1–3 peptides · async physician approval · no live visit required',
                'mini_tiers' => [
                    ['label' => '1 Peptide',  'price' => '[Price on formulary upload] /mo', 'note' => 'Async physician review included'],
                    ['label' => '2 Peptides', 'price' => '[Price on formulary upload] /mo', 'note' => 'Async physician review included'],
                    ['label' => '3 Peptides', 'price' => '[Price on formulary upload] /mo', 'note' => 'Async physician review included'],
                ],
                'features' => [
                    'AI intake builds your personalized peptide protocol',
                    'Async physician chart review and approval — no video call needed',
                    '$49 assessment credit applied to your first order',
                    'Monthly refill, delivered to your door',
                    'Stack up to 3 peptide protocols',
                ],
                'waitlist_heading' => 'Get notified when pricing drops',
                'waitlist_body' => "Peptide formulary is being finalized. Join the waitlist — we'll email you the moment it's live.",
                'waitlist_placeholder' => 'your@email.com',
                'waitlist_cta_label' => 'Notify Me When Live →',
                'success_heading' => "You're on the list",
                'success_body' => "We'll notify you the moment peptide pricing goes live. You'll be first.",
                'fallback_cta_label' => 'Start $49 Assessment Now →',
                'fallback_cta_url' => '#concierge',
                'fallback_note' => 'PEPTIDE PRICING AVAILABLE ON FORMULARY UPLOAD',
            ],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    TextInput::make('eyebrow')
                        ->label('Section eyebrow tag')
                        ->maxLength(120),
                ]),

            Repeater::make('main_tiers')
                ->label('Main pricing cards (max 2 — Blueprint + Featured)')
                ->schema([
                    TextInput::make('pill')->label('Top pill text')->maxLength(120),
                    TextInput::make('pill_emoji')->label('Pill emoji (optional)')->maxLength(8),
                    Select::make('accent')
                        ->options(['sage' => 'Sage (Blueprint card)', 'gold' => 'Gold (Featured / TRT)'])
                        ->required()
                        ->native(false),
                    TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                    TextInput::make('subtitle')->maxLength(255)->columnSpanFull(),
                    TextInput::make('price')->maxLength(60),
                    TextInput::make('price_suffix')->label('Price suffix')->maxLength(120),
                    TextInput::make('price_note_micro')
                        ->label('Tiny note next to price')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('lto_banner')
                        ->label('Limited-time-offer banner')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    TextInput::make('callout_heading')->label('Callout heading')->maxLength(255)->columnSpanFull(),
                    Textarea::make('callout_body')->label('Callout body')->rows(3)->maxLength(500)->columnSpanFull(),
                    Repeater::make('features')
                        ->simple(TextInput::make('feature')->required()->maxLength(255))
                        ->columnSpanFull(),
                    TextInput::make('cta_label')->maxLength(120),
                    TextInput::make('cta_url')->maxLength(2048),
                    TextInput::make('cta_micro')->label('Tiny note under CTA')->maxLength(255)->columnSpanFull(),
                    TextInput::make('secondary_label')->label('Secondary link label')->maxLength(120),
                    TextInput::make('secondary_url')->label('Secondary link URL')->maxLength(2048),
                    Textarea::make('route')
                        ->label('Route summary')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Repeater::make('guarantees')
                        ->label('Footer guarantees')
                        ->simple(TextInput::make('guarantee')->maxLength(255))
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->maxItems(2),

            Section::make('Peptide / waitlist card')
                ->description('Optional full-width card below the main grid.')
                ->components([
                    Select::make('peptide_card.enabled')
                        ->label('Show this card')
                        ->options(['1' => 'Show', '0' => 'Hide'])
                        ->default('1')
                        ->native(false),
                    TextInput::make('peptide_card.eyebrow_main')->label('Primary eyebrow')->maxLength(255),
                    TextInput::make('peptide_card.eyebrow_secondary')->label('Secondary eyebrow (e.g. waitlist tag)')->maxLength(255),
                    TextInput::make('peptide_card.title')->maxLength(255),
                    TextInput::make('peptide_card.subtitle')->maxLength(500),
                    Repeater::make('peptide_card.mini_tiers')
                        ->label('Mini tier cards (e.g. 1/2/3 peptides)')
                        ->schema([
                            TextInput::make('label')->required()->maxLength(60),
                            TextInput::make('price')->maxLength(120),
                            TextInput::make('note')->maxLength(120),
                        ])
                        ->columns(3),
                    Repeater::make('peptide_card.features')
                        ->label('Features')
                        ->simple(TextInput::make('feature')->maxLength(255)),
                    TextInput::make('peptide_card.waitlist_heading')->maxLength(120),
                    Textarea::make('peptide_card.waitlist_body')->rows(2)->maxLength(500),
                    TextInput::make('peptide_card.waitlist_placeholder')->maxLength(60),
                    TextInput::make('peptide_card.waitlist_cta_label')->maxLength(60),
                    TextInput::make('peptide_card.success_heading')->maxLength(120),
                    Textarea::make('peptide_card.success_body')->rows(2)->maxLength(500),
                    TextInput::make('peptide_card.fallback_cta_label')->maxLength(120),
                    TextInput::make('peptide_card.fallback_cta_url')->maxLength(2048),
                    TextInput::make('peptide_card.fallback_note')->maxLength(255),
                ]),
        ];
    }
}
