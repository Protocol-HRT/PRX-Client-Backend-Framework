<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
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
                    CopyFields::inline('heading')->required(),
                    SectionImagePicker::make('mark_image')
                        ->label('Rail emblem')
                        ->helperText('Small image rendered at the top of the vertical rail (e.g. a logo mark). Optional.'),
                    CopyFields::inline('lead')->columnSpanFull(),
                ]),
            Repeater::make('steps')
                ->label('Steps')
                ->schema([
                    CopyFields::inline('title')->required(),
                    CopyFields::inline('meta')
                        ->label('Sub label')
                        ->helperText('Muted line under the title (e.g. a duration).'),
                    CopyFields::prose('body')->columnSpanFull(),
                    Repeater::make('bullets')
                        ->label('Bullet list')
                        ->schema([
                            CopyFields::inline('text')->required(),
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
