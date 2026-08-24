<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
                    CopyFields::inline('eyebrow')
                        ->label('Section eyebrow tag'),
                ]),

            Repeater::make('main_tiers')
                ->label('Main pricing cards (max 2 — Blueprint + Featured)')
                ->schema([
                    CopyFields::inline('pill')->label('Top pill text'),
                    TextInput::make('pill_emoji')->label('Pill emoji (optional)')->maxLength(8),
                    Select::make('accent')
                        ->options(['sage' => 'Sage (Blueprint card)', 'gold' => 'Gold (Featured / TRT)'])
                        ->required()
                        ->native(false),
                    CopyFields::inline('title')->required()->columnSpanFull(),
                    CopyFields::inline('subtitle')->columnSpanFull(),
                    TextInput::make('price')->maxLength(60),
                    TextInput::make('price_suffix')->label('Price suffix')->maxLength(120),
                    CopyFields::inline('price_note_micro')
                        ->label('Tiny note next to price')
                        ->columnSpanFull(),
                    CopyFields::inline('lto_banner')
                        ->label('Limited-time-offer banner')

                        ->columnSpanFull(),
                    CopyFields::inline('callout_heading')->label('Callout heading')->columnSpanFull(),
                    CopyFields::prose('callout_body')->label('Callout body')->columnSpanFull(),
                    Repeater::make('features')
                        ->simple(CopyFields::inline('feature')->required())
                        ->columnSpanFull(),
                    TextInput::make('cta_label')->maxLength(120),
                    TextInput::make('cta_url')->maxLength(2048),
                    CopyFields::inline('cta_micro')->label('Tiny note under CTA')->columnSpanFull(),
                    TextInput::make('secondary_label')->label('Secondary link label')->maxLength(120),
                    TextInput::make('secondary_url')->label('Secondary link URL')->maxLength(2048),
                    CopyFields::inline('route')
                        ->label('Route summary')

                        ->columnSpanFull(),
                    Repeater::make('guarantees')
                        ->label('Footer guarantees')
                        ->simple(CopyFields::inline('guarantee'))
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
                    CopyFields::inline('peptide_card.eyebrow_main')->label('Primary eyebrow'),
                    CopyFields::inline('peptide_card.eyebrow_secondary')->label('Secondary eyebrow (e.g. waitlist tag)'),
                    CopyFields::inline('peptide_card.title'),
                    CopyFields::inline('peptide_card.subtitle'),
                    Repeater::make('peptide_card.mini_tiers')
                        ->label('Mini tier cards (e.g. 1/2/3 peptides)')
                        ->schema([
                            CopyFields::inline('label')->required(),
                            TextInput::make('price')->maxLength(120),
                            CopyFields::inline('note'),
                        ])
                        ->columns(3),
                    Repeater::make('peptide_card.features')
                        ->label('Features')
                        ->simple(CopyFields::inline('feature')),
                    CopyFields::inline('peptide_card.waitlist_heading'),
                    CopyFields::prose('peptide_card.waitlist_body'),
                    TextInput::make('peptide_card.waitlist_placeholder')->maxLength(60),
                    TextInput::make('peptide_card.waitlist_cta_label')->maxLength(60),
                    CopyFields::inline('peptide_card.success_heading'),
                    CopyFields::prose('peptide_card.success_body'),
                    TextInput::make('peptide_card.fallback_cta_label')->maxLength(120),
                    TextInput::make('peptide_card.fallback_cta_url')->maxLength(2048),
                    CopyFields::inline('peptide_card.fallback_note'),
                ]),
        ];
    }
}
