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
            'eyebrow' => null,
            'main_tiers' => [],
            'peptide_card' => [
                'enabled' => false,
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
