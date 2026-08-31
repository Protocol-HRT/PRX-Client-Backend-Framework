<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

class HighlightBannerSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::HighlightBanner;
    }

    public function label(): string
    {
        return 'Highlight banner';
    }

    public function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public function description(): ?string
    {
        return 'Slim band of short icon + text highlights (trust markers, key benefits). Columns never exceed the number of highlights, so the row always fills evenly.';
    }

    public function defaults(): array
    {
        return [
            'items' => [],
            'icon_placement' => 'left',
            'per_row' => '4',
            'bordered' => false,
            'theme' => 'cream',
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Layout')
                ->columns(2)
                ->components([
                    Select::make('icon_placement')
                        ->options(['left' => 'Icon left of text', 'top' => 'Icon above text'])
                        ->default('left')
                        ->native(false),
                    Select::make('per_row')
                        ->label('Items per row')
                        ->options(['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'])
                        ->default('4')
                        ->native(false)
                        ->helperText('Desktop column count — item width follows. Collapses on small screens.'),
                    Toggle::make('bordered')
                        ->label('Item borders')
                        ->helperText('Outline each highlight as a card.'),
                    Select::make('theme')
                        ->options(['light' => 'Light', 'dark' => 'Dark', 'cream' => 'Cream'])
                        ->default('cream')
                        ->native(false),
                ]),
            Repeater::make('items')
                ->label('Highlights')
                ->schema([
                    TextInput::make('icon_class')
                        ->label('Icon')
                        ->maxLength(64)
                        ->placeholder('ti ti-truck')
                        ->hintIcon(Heroicon::InformationCircle, 'A Tabler icon class — browse them at tabler.io/icons and use "ti ti-" plus the icon name. An emoji works too. This is the usual choice; use the image below only when you need a specific piece of artwork.')
                        ->helperText('Used in preference to the image, if both are set.'),
                    SectionImagePicker::make('icon')
                        ->label('Icon image')
                        ->helperText('Optional. An SVG or PNG, for when an icon class will not do.'),
                    CopyFields::inline('text')
                        ->required()
                        ->helperText('Line breaks are kept — two short lines render as in the design.'),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['text'] ?? null),
        ];
    }

    public function fieldKinds(): array
    {
        return ['items.*.icon' => 'image'];
    }
}
