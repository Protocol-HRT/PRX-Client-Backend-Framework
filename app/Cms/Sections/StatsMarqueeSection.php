<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class StatsMarqueeSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::StatsMarquee;
    }

    public function label(): string
    {
        return 'Stats marquee';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public function description(): ?string
    {
        return 'Auto-scrolling strip of value/label pairs.';
    }

    public function defaults(): array
    {
        return [
            'items' => [
                ['value' => '50', 'label' => 'States Licensed'],
                ['value' => '10,000+', 'label' => 'Patients'],
                ['value' => '24/7', 'label' => 'AI Support'],
            ],
        ];
    }

    public function formSchema(): array
    {
        return [
            Repeater::make('items')
                ->label('Stats')
                ->schema([
                    TextInput::make('value')->required()->maxLength(60),
                    TextInput::make('label')->required()->maxLength(120),
                ])
                ->columns(2)
                ->reorderable()
                ->minItems(1)
                ->columnSpanFull(),
        ];
    }
}
