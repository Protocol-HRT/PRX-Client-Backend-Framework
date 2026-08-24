<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class TextBlockSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::TextBlock;
    }

    public function label(): string
    {
        return 'Text block';
    }

    public function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function description(): ?string
    {
        return 'Generic prose section: optional eyebrow + heading + rich text body. Useful for "About", "Our Approach", legal copy, etc.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'body' => null,
            'alignment' => 'left',
            'theme' => 'light',
        ];
    }

    public function formSchema(): array
    {
        return [
            TextInput::make('eyebrow')->maxLength(120),
            TextInput::make('heading')->maxLength(255),
            RichEditor::make('body')->columnSpanFull(),
            Select::make('alignment')
                ->options(['left' => 'Left', 'center' => 'Center'])
                ->default('left')
                ->native(false),
            Select::make('theme')
                ->options(['light' => 'Light', 'dark' => 'Dark', 'cream' => 'Cream'])
                ->default('light')
                ->native(false),
        ];
    }
}
