<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class FeaturesGridSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::FeaturesGrid;
    }

    public function label(): string
    {
        return 'Features grid';
    }

    public function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function description(): ?string
    {
        return 'Multi-column grid of icon + title + description cards. Use for service overviews, value props, etc.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'lead' => null,
            'columns' => '3',
            'features' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('heading')->maxLength(255),
                    Textarea::make('lead')->rows(3)->maxLength(500),
                    Select::make('columns')->options(['2' => '2 columns', '3' => '3 columns', '4' => '4 columns'])->default('3')->native(false),
                ]),
            Repeater::make('features')
                ->label('Features')
                ->schema([
                    TextInput::make('icon')->maxLength(64)->helperText('Heroicon name (e.g. heroicon-o-bolt) or emoji.'),
                    TextInput::make('title')->required()->maxLength(255),
                    Textarea::make('body')->rows(3)->required()->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
        ];
    }
}
