<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
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
                    SectionImagePicker::make('image')->label('Headshot'),
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

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['quotes.*.image' => 'image'];
    }
}
