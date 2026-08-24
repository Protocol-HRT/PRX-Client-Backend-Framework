<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
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
                    CopyFields::inline('eyebrow'),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('emphasis')->helperText('Italic run on the second line.'),
                    CopyFields::inline('lead'),
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
