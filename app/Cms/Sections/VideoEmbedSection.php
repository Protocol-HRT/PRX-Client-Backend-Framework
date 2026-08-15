<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class VideoEmbedSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::VideoEmbed;
    }

    public function label(): string
    {
        return 'Video embed';
    }

    public function icon(): string
    {
        return 'heroicon-o-play-circle';
    }

    public function description(): ?string
    {
        return 'YouTube or Vimeo embed with optional heading + caption + poster image.';
    }

    public function defaults(): array
    {
        return [
            'heading' => null,
            'caption' => null,
            'video_url' => null,
            'poster_image' => null,
            'theme' => 'light',
        ];
    }

    public function formSchema(): array
    {
        return [
            TextInput::make('heading')->maxLength(255),
            TextInput::make('caption')->maxLength(500),
            TextInput::make('video_url')->required()->url()->maxLength(2048)->helperText('YouTube or Vimeo URL.'),
            SectionImagePicker::make('poster_image')->label('Poster image'),
            Select::make('theme')->options(['light' => 'Light', 'dark' => 'Dark', 'cream' => 'Cream'])->default('light')->native(false),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['poster_image' => 'image'];
    }
}
