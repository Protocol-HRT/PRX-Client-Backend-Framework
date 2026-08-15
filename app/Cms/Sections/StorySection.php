<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
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
            'eyebrow' => 'The Story Behind [Brand Name]',
            'heading' => 'Trusted by experts,',
            'emphasis' => 'built for you.',
            'lead' => "[Brand Name] wasn't built in a boardroom. It was built by physicians who lived it, treated it, and refused to accept the limits of conventional medicine.",
            'physicians' => [
                [
                    'name' => 'Dr. [First Name] [Last Name]',
                    'title' => 'Founding Physician · Lifestyle Management Expert',
                    'badge' => 'Published Author & Clinician',
                    'image' => null,
                    'image_alt' => 'Dr. [First Name] [Last Name], Founding Physician at [Brand Name]',
                    'body' => "Dr. [First Name] [Last Name]'s path to founding this practice began with a personal experience that conventional medicine couldn't solve. That pursuit of answers — of what the body is truly capable of with the right hormones, the right peptides, and the right protocol — became [Brand Name].",
                ],
                [
                    'name' => 'Dr. [First Name] [Last Name]',
                    'title' => 'Chief Medical Officer · ER Physician · Peptide Expert',
                    'badge' => 'World-Class Hormone Optimization Specialist',
                    'image' => null,
                    'image_alt' => 'Dr. [First Name] [Last Name], Chief Medical Officer at [Brand Name]',
                    'body' => 'Dr. [First Name] [Last Name] brings decades of frontline ER medicine and deep mastery of hormone and peptide science that most physicians never study. One of the most respected voices in hormone optimization, they built the clinical foundation that makes [Brand Name] unlike anything else in telemedicine.',
                ],
            ],
            'pull_quote' => "Built by physicians. Proven by the world's best. Made for everyone.",
            'pull_quote_attribution' => '— The Founding Physicians of [Brand Name]',
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent (italic sage)')
                        ->maxLength(255)
                        ->helperText('Optional sage italic run rendered after the heading.'),
                    Textarea::make('lead')->rows(3)->maxLength(500),
                ]),
            Repeater::make('physicians')
                ->label('Physicians')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('title')->label('Title / role')->maxLength(255),
                    TextInput::make('badge')
                        ->maxLength(255)
                        ->helperText('Gold pill below the title (e.g. "Author: Some Book").'),
                    SectionImagePicker::make('image')->label('Portrait image'),
                    TextInput::make('image_alt')->maxLength(255),
                    Textarea::make('body')
                        ->label('Bio')
                        ->rows(5)
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
                    Textarea::make('pull_quote')->rows(2)->maxLength(500),
                    TextInput::make('pull_quote_attribution')
                        ->label('Attribution')
                        ->maxLength(255),
                ]),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['physicians.*.image' => 'image'];
    }
}
