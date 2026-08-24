<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
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
                    CopyFields::inline('eyebrow')->label('Sage eyebrow'),
                    CopyFields::inline('heading')->required(),
                    CopyFields::inline('emphasis')
                        ->label('Heading accent (sage)')
                        ->helperText('Final-clause word(s) rendered in sage green.'),
                    CopyFields::prose('description')
                        ->label('Intro description')

                        ->helperText('Short paragraph rendered under the heading in the intro column.'),
                    TextInput::make('cta_label')->label('CTA label')->maxLength(60),
                    TextInput::make('cta_url')->label('CTA URL')->maxLength(2048),
                    SectionImagePicker::make('image')->label('Intro image'),
                    TextInput::make('image_alt')->maxLength(255),
                ]),
            Repeater::make('faqs')
                ->label('Questions')
                ->schema([
                    CopyFields::inline('q')->label('Question')->required()->columnSpanFull(),
                    CopyFields::prose('a')->label('Answer')->required()->columnSpanFull(),
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
