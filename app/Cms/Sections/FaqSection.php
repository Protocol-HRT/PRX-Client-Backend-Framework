<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class FaqSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Faq;
    }

    public function label(): string
    {
        return 'FAQ';
    }

    public function icon(): string
    {
        return 'heroicon-o-question-mark-circle';
    }

    public function description(): ?string
    {
        return 'Light-theme FAQ accordion. Centered max-w-3xl column. Sage section-label eyebrow → display heading with sage emphasis on the last word → bordered disclosure rows. Alpine-driven (one open at a time).';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'description' => null,
            'cta_label' => null,
            'cta_url' => null,
            'image' => null,
            'image_alt' => null,
            'faqs' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    TextInput::make('eyebrow')->label('Sage eyebrow')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent (sage)')
                        ->maxLength(255)
                        ->helperText('Final-clause word(s) rendered in sage green.'),
                    Textarea::make('description')
                        ->label('Intro description')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Short paragraph rendered under the heading in the intro column.'),
                    TextInput::make('cta_label')->label('CTA label')->maxLength(60),
                    TextInput::make('cta_url')->label('CTA URL')->maxLength(2048),
                    SectionImagePicker::make('image')->label('Intro image'),
                    TextInput::make('image_alt')->maxLength(255),
                ]),
            Repeater::make('faqs')
                ->label('Questions')
                ->schema([
                    TextInput::make('q')->label('Question')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('a')->label('Answer')->rows(4)->required()->columnSpanFull(),
                ])
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['q'] ?? null),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['image' => 'image'];
    }
}
