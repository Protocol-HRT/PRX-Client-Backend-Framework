<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class TimelineSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Timeline;
    }

    public function label(): string
    {
        return 'Timeline (vertical steps)';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrow-long-down';
    }

    public function description(): ?string
    {
        return 'Centered heading + lead, then a vertical center rail with dot markers and steps alternating right/left of the rail. Each step: title, small sub label, body, optional bullet list. Optional emblem image at the top of the rail.';
    }

    public function defaults(): array
    {
        return [
            'heading' => null,
            'lead' => null,
            'mark_image' => null,
            'steps' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->columns(2)
                ->components([
                    TextInput::make('heading')->required()->maxLength(255),
                    SectionImagePicker::make('mark_image')
                        ->label('Rail emblem')
                        ->helperText('Small image rendered at the top of the vertical rail (e.g. a logo mark). Optional.'),
                    Textarea::make('lead')->rows(3)->maxLength(500)->columnSpanFull(),
                ]),
            Repeater::make('steps')
                ->label('Steps')
                ->schema([
                    TextInput::make('title')->required()->maxLength(255),
                    TextInput::make('meta')
                        ->label('Sub label')
                        ->maxLength(255)
                        ->helperText('Muted line under the title (e.g. a duration).'),
                    Textarea::make('body')->rows(4)->maxLength(2000)->columnSpanFull(),
                    Repeater::make('bullets')
                        ->label('Bullet list')
                        ->schema([
                            TextInput::make('text')->required()->maxLength(255),
                        ])
                        ->reorderable()
                        ->columnSpanFull()
                        ->itemLabel(fn (array $state): ?string => $state['text'] ?? null),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['mark_image' => 'image'];
    }
}
