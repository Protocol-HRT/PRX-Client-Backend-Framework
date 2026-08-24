<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class ImageTextSplitSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::ImageTextSplit;
    }

    public function label(): string
    {
        return 'Image + text split';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function description(): ?string
    {
        return '50/50 image and prose layout. Toggle the layout to flip image position. Optional floating frosted cards over the image (stat value or icon + text + star rating).';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'lead' => null,
            'body' => null,
            'image' => null,
            'image_alt' => null,
            'cta_label' => null,
            'cta_url' => null,
            'image_right' => false,
            'theme' => 'light',
            'float_cards' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Copy')
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('heading')->maxLength(255),
                    Textarea::make('lead')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Short paragraph rendered under the heading, beside the body column.')
                        ->columnSpanFull(),
                    RichEditor::make('body')->columnSpanFull(),
                    TextInput::make('cta_label')->maxLength(60),
                    TextInput::make('cta_url')->maxLength(2048),
                ])
                ->columns(2),
            Section::make('Image')
                ->columns(2)
                ->components([
                    SectionImagePicker::make('image')->label('Image'),
                    TextInput::make('image_alt')->maxLength(255),
                    Toggle::make('image_right')->label('Image on the right')->helperText('Default is image on the left.'),
                    Select::make('theme')->options(['light' => 'Light', 'dark' => 'Dark', 'cream' => 'Cream'])->default('light')->native(false),
                ]),
            Repeater::make('float_cards')
                ->label('Floating cards (over the image)')
                ->maxItems(2)
                ->schema([
                    Select::make('position')
                        ->options(['bottom-left' => 'Bottom left', 'top-right' => 'Top right'])
                        ->default('bottom-left')
                        ->required()
                        ->native(false),
                    SectionImagePicker::make('icon')->label('Icon / logo'),
                    TextInput::make('value')
                        ->label('Stat value')
                        ->maxLength(20)
                        ->helperText('Large figure rendered above the text (e.g. "15+"). Leave empty for icon/text-only cards.'),
                    TextInput::make('text')->maxLength(160),
                    TextInput::make('rating_value')
                        ->label('Star rating (0–5)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(5)
                        ->step(0.1)
                        ->helperText('Renders a stars row when set.'),
                    TextInput::make('rating_text')
                        ->maxLength(120)
                        ->helperText('Line next to the stars (e.g. "4.9/5 · 200+ reviews").'),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['text'] ?? $state['value'] ?? null),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return [
            'image' => 'image',
            'float_cards.*.icon' => 'image',
        ];
    }
}
