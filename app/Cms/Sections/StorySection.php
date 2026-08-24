<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class StorySection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Story;
    }

    public function label(): string
    {
        return 'Founders / story';
    }

    public function icon(): string
    {
        return 'heroicon-o-book-open';
    }

    public function description(): ?string
    {
        return 'Light-theme founders story: left-aligned headline + lead, 2-card grid of physician bios (round portrait + name + sage title + gold badge + bio), closing manifesto block on mint background.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'lead' => null,
            'physicians' => [],
            'pull_quote' => null,
            'pull_quote_attribution' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    CopyFields::inline('eyebrow'),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('emphasis')
                        ->label('Heading accent (italic sage)')
                        ->helperText('Optional sage italic run rendered after the heading.'),
                    CopyFields::inline('lead'),
                ]),
            Repeater::make('physicians')
                ->label('Physicians')
                ->schema([
                    CopyFields::inline('name')->required(),
                    CopyFields::inline('title')->label('Title / role'),
                    CopyFields::inline('badge')
                        ->helperText('Gold pill below the title (e.g. "Author: Some Book").'),
                    SectionImagePicker::make('image')->label('Portrait image'),
                    TextInput::make('image_alt')->maxLength(255),
                    CopyFields::prose('body')
                        ->label('Bio')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
            Section::make('Closing manifesto')
                ->description('Italic pull quote in a mint-bg card at the foot of the section.')
                ->components([
                    CopyFields::inline('pull_quote'),
                    CopyFields::inline('pull_quote_attribution')
                        ->label('Attribution'),
                ]),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['physicians.*.image' => 'image'];
    }
}
