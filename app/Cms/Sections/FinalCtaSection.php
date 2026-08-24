<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class FinalCtaSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::FinalCta;
    }

    public function label(): string
    {
        return 'Final call-to-action';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrow-right-circle';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'lead' => null,
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
                    TextInput::make('emphasis')->maxLength(255)->helperText('Italic run on the second line.'),
                    Textarea::make('lead')->rows(3)->maxLength(500),
                ]),
            Section::make('Calls to action')
                ->columns(2)
                ->components([
                    TextInput::make('primary_cta_label')->maxLength(60),
                    TextInput::make('primary_cta_url')->maxLength(2048),
                    TextInput::make('secondary_cta_label')->maxLength(60),
                    TextInput::make('secondary_cta_url')->maxLength(2048),
                ]),
        ];
    }
}
