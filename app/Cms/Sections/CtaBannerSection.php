<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CtaBannerSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::CtaBanner;
    }

    public function label(): string
    {
        return 'CTA banner';
    }

    public function icon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public function description(): ?string
    {
        return 'Single full-width call-to-action strip with eyebrow, headline, sub, and one or two buttons.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'sub' => null,
            'background_image' => null,
            'theme' => 'dark',
            'primary_cta_label' => null,
            'primary_cta_url' => null,
            'secondary_cta_label' => null,
            'secondary_cta_url' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Copy')
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    Textarea::make('sub')->rows(3)->maxLength(500),
                ]),
            Section::make('Calls to action')
                ->columns(2)
                ->components([
                    TextInput::make('primary_cta_label')->maxLength(60),
                    TextInput::make('primary_cta_url')->maxLength(2048),
                    TextInput::make('secondary_cta_label')->maxLength(60),
                    TextInput::make('secondary_cta_url')->maxLength(2048),
                ]),
            Section::make('Style')
                ->columns(2)
                ->components([
                    TextInput::make('background_image')->label('Background image path')->maxLength(2048),
                    Select::make('theme')->options(['light' => 'Light', 'dark' => 'Dark', 'cream' => 'Cream'])->default('dark')->native(false),
                ]),
        ];
    }
}
