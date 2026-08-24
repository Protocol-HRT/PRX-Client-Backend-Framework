<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
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
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'rating_stats' => [],
            'quotes' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->columns(2)
                ->components([
                    CopyFields::inline('eyebrow')->label('Editorial tag'),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('emphasis')
                        ->label('Heading accent (italic gold)')
                        ->columnSpanFull(),
                ]),
            Repeater::make('rating_stats')
                ->label('Rating stats (right of header — leave empty to hide)')
                ->schema([
                    CopyFields::inline('value')->required(),
                    CopyFields::inline('label')->required(),
                ])
                ->columns(2)
                ->reorderable()
                ->maxItems(4)
                ->columnSpanFull(),
            Repeater::make('quotes')
                ->label('Testimonials')
                ->schema([
                    CopyFields::inline('protocol')
                        ->label('Protocol tag (mono uppercase)')
                        ->helperText('e.g. "HORMONE OPTIMIZATION", "PHYSICIAN ENDORSEMENT".'),
                    TextInput::make('stars')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(5)
                        ->default(5)
                        ->helperText('0–5 gold stars rendered above the quote.'),
                    CopyFields::inline('name')->required(),
                    CopyFields::inline('title')->label('Title / role'),
                    SectionImagePicker::make('image')->label('Headshot'),
                    TextInput::make('initials')
                        ->maxLength(4)
                        ->helperText('Fallback when no image is set.'),
                    CopyFields::inline('quote')->required()->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['quotes.*.image' => 'image'];
    }
}
