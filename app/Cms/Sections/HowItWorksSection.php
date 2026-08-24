<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class HowItWorksSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::HowItWorks;
    }

    public function label(): string
    {
        return 'How it works (process)';
    }

    public function icon(): string
    {
        return 'heroicon-o-list-bullet';
    }

    public function description(): ?string
    {
        return 'Light-theme process explanation. 2-col header (heading left, lead + dark CTA right), then a 3-col step grid with circular step-numbers and connector lines.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'lead' => null,
            'cta_label' => null,
            'cta_url' => null,
            'steps' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->columns(2)
                ->components([
                    TextInput::make('eyebrow')->label('Eyebrow (sage)')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent (sage)')
                        ->maxLength(255)
                        ->helperText('Sage run rendered after the heading.'),
                    Textarea::make('lead')->rows(3)->maxLength(500)->columnSpanFull(),
                    TextInput::make('cta_label')->maxLength(60),
                    TextInput::make('cta_url')->maxLength(2048),
                ]),
            Repeater::make('steps')
                ->label('Steps')
                ->schema([
                    TextInput::make('number')
                        ->required()
                        ->maxLength(8)
                        ->helperText('e.g. 01, 02, 03 — rendered in the circular badge.'),
                    TextInput::make('title')->required()->maxLength(255),
                    TextInput::make('meta')
                        ->label('Sub label (sage mono)')
                        ->maxLength(255)
                        ->helperText('Tiny line above the title (e.g. "Takes about 5 minutes").'),
                    Textarea::make('body')->rows(4)->required()->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => isset($state['number'], $state['title']) ? "{$state['number']} · {$state['title']}" : null),
        ];
    }
}
