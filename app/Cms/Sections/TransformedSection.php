<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class TransformedSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Transformed;
    }

    public function label(): string
    {
        return 'Ambassadors / featured proof';
    }

    public function icon(): string
    {
        return 'heroicon-o-trophy';
    }

    public function description(): ?string
    {
        return 'Dark-theme ambassador-card grid. 2-col header (heading left, lead right). Each card has a 120×120 circular portrait at the top, 5 gold stars, italic quote, and a divider separating name+title (left) from a small protocol pill (right).';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'lead' => null,
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
                        ->helperText('Gold italic run rendered after the heading.'),
                    Textarea::make('lead')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Right column of the header — lead paragraph that introduces the ambassadors.')
                        ->columnSpanFull(),
                ]),
            Repeater::make('quotes')
                ->label('Ambassador cards')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('title')->label('Title / role')->maxLength(255),
                    TextInput::make('protocol')
                        ->label('Protocol pill (mono uppercase)')
                        ->maxLength(120)
                        ->helperText('Small gold pill on the right of the footer (e.g. "Performance & Hormone Optimization").'),
                    SectionImagePicker::make('image')->label('Portrait image'),
                    TextInput::make('image_alt')->maxLength(255),
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
