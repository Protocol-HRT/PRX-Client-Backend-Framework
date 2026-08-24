<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Select;
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
                    CopyFields::inline('eyebrow'),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('sub'),
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
                    SectionImagePicker::make('background_image')->label('Background image'),
                    Select::make('theme')->options(['light' => 'Light', 'dark' => 'Dark', 'cream' => 'Cream'])->default('dark')->native(false),
                ]),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['background_image' => 'image'];
    }
}
