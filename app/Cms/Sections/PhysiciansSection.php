<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class PhysiciansSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Physicians;
    }

    public function label(): string
    {
        return 'Physicians spotlight';
    }

    public function icon(): string
    {
        return 'heroicon-o-user-group';
    }

    public function description(): ?string
    {
        return 'Light-theme grid of physician spotlight cards (portrait + credentials chip + 3 stats + pull quote) with a mint trust-badge footer below.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'Meet Your Physicians',
            'heading' => 'Real doctors.',
            'heading_emphasis' => 'Real credentials.',
            'heading_third_line' => 'Real results.',
            'lead' => 'Every protocol is designed and reviewed by board-certified physicians with decades of clinical experience in hormone and peptide optimization.',
            'physicians' => [
                [
                    'name' => 'Dr. [First Name] [Last Name]',
                    'credentials' => 'MD, FACS',
                    'title' => 'Founding Physician & CEO',
                    'specialty' => 'Lifestyle Medicine · Longevity Expert',
                    'image' => null,
                    'image_alt' => 'Dr. [First Name] [Last Name], MD — Founding Physician and CEO of [Brand Name]',
                    'accent_color' => '#5a8a5e',
                    'stats' => [
                        ['value' => '20+',     'label' => 'Years Practice'],
                        ['value' => '35,000+', 'label' => 'Patients Treated'],
                        ['value' => 'Author',  'label' => 'Published Author'],
                    ],
                    'quote' => '"Conventional medicine treats symptoms. We treat the root cause — your hormones, your biology, your potential."',
                ],
                [
                    'name' => 'Dr. [First Name] [Last Name]',
                    'credentials' => 'MD, FACEP',
                    'title' => 'Chief Medical Officer',
                    'specialty' => 'Emergency Medicine · Peptide Science',
                    'image' => null,
                    'image_alt' => 'Dr. [First Name] [Last Name], MD — Chief Medical Officer at [Brand Name]',
                    'accent_color' => '#c9a84c',
                    'stats' => [
                        ['value' => '25+',    'label' => 'Years Practice'],
                        ['value' => 'CMO',    'label' => '[Brand Name]'],
                        ['value' => 'Expert', 'label' => 'Peptide & HRT Science'],
                    ],
                    'quote' => '"In the ER I saw what hormonal decline does to people. Now I get to reverse it — before it becomes a crisis."',
                ],
            ],
            'trust_badges' => [
                ['icon' => '🏥', 'label' => 'Board-Certified Physicians'],
                ['icon' => '⚕️', 'label' => 'Licensed in All 50 States'],
                ['icon' => '🔬', 'label' => 'Evidence-Based Protocols'],
                ['icon' => '📋', 'label' => 'Physician-Reviewed Every Rx'],
            ],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('heading_emphasis')
                        ->label('Heading accent (italic sage)')
                        ->maxLength(255)
                        ->helperText('Optional italic green run rendered after the heading.'),
                    TextInput::make('heading_third_line')
                        ->label('Heading third line')
                        ->maxLength(255)
                        ->helperText('Optional third line below the accent run.'),
                    Textarea::make('lead')->rows(3)->maxLength(500),
                ]),
            Repeater::make('physicians')
                ->label('Physicians')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('credentials')->maxLength(120)->helperText('e.g. "MD, FACS"'),
                    TextInput::make('title')->label('Title / role')->maxLength(255),
                    TextInput::make('specialty')->maxLength(255),
                    ColorPicker::make('accent_color')
                        ->default('#5a8a5e')
                        ->helperText('Hex color used for the top accent bar, credentials chip, and quote rule.'),
                    SectionImagePicker::make('image')->label('Portrait image'),
                    TextInput::make('image_alt')->maxLength(255),
                    Textarea::make('quote')->rows(3)->maxLength(500)->columnSpanFull(),
                    Repeater::make('stats')
                        ->label('Stats (3 items)')
                        ->schema([
                            TextInput::make('value')->required()->maxLength(60),
                            TextInput::make('label')->required()->maxLength(120),
                        ])
                        ->columns(2)
                        ->minItems(0)
                        ->maxItems(3)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
            Section::make('Trust badges (footer strip)')
                ->components([
                    Repeater::make('trust_badges')
                        ->schema([
                            TextInput::make('icon')->maxLength(8)->helperText('Emoji or single character.'),
                            TextInput::make('label')->required()->maxLength(120),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return ['physicians.*.image' => 'image'];
    }
}
