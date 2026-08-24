<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
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
                    CopyFields::inline('eyebrow')->label('Editorial tag'),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('emphasis')
                        ->label('Heading accent (italic gold)')
                        ->helperText('Gold italic run rendered after the heading.'),
                    CopyFields::inline('lead')

                        ->helperText('Right column of the header — lead paragraph that introduces the ambassadors.')
                        ->columnSpanFull(),
                ]),
            Repeater::make('quotes')
                ->label('Ambassador cards')
                ->schema([
                    CopyFields::inline('name')->required(),
                    CopyFields::inline('title')->label('Title / role'),
                    CopyFields::inline('protocol')
                        ->label('Protocol pill (mono uppercase)')
                        ->helperText('Small gold pill on the right of the footer (e.g. "Performance & Hormone Optimization").'),
                    SectionImagePicker::make('image')->label('Portrait image'),
                    TextInput::make('image_alt')->maxLength(255),
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
