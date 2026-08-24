<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
        return 'Physician spotlight card(s): round portrait, role eyebrow, name heading, specialty line, bio paragraph, and a row of credential badge pills. Optional section header and trust-badge strip.';
    }

    public function defaults(): array
    {
        return [
            'theme' => 'light',
            'eyebrow' => null,
            'heading' => null,
            'heading_emphasis' => null,
            'lead' => null,
            'physicians' => [],
            'trust_badges' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Select::make('theme')
                ->options(['light' => 'Light', 'dark' => 'Dark'])
                ->default('light')
                ->native(false)
                ->helperText('Dark renders the spotlight card on a near-black panel with light text and translucent badge pills.'),
            Section::make('Section header (optional)')
                ->description('Leave empty to render the spotlight cards alone.')
                ->collapsed()
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('heading')->maxLength(255),
                    TextInput::make('heading_emphasis')
                        ->label('Heading accent (italic)')
                        ->maxLength(255)
                        ->helperText('Optional accent run rendered after the heading.'),
                    Textarea::make('lead')->rows(3)->maxLength(500),
                ]),
            Repeater::make('physicians')
                ->label('Physicians')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Rendered as given — include credentials if wanted (e.g. "Dr. Jane Smith, MD").'),
                    TextInput::make('title')
                        ->label('Role eyebrow')
                        ->maxLength(255)
                        ->helperText('Small line above the name (e.g. "Medical Director").'),
                    TextInput::make('specialty')
                        ->label('Specialty line')
                        ->maxLength(255)
                        ->helperText('Line under the name (e.g. "Board-Certified · 20+ Years Clinical Experience").'),
                    SectionImagePicker::make('image')->label('Portrait image'),
                    TextInput::make('image_alt')->maxLength(255),
                    Textarea::make('bio')
                        ->label('Bio paragraph')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Repeater::make('badges')
                        ->label('Credential badge pills')
                        ->simple(TextInput::make('badge')->required()->maxLength(120))
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
            Section::make('Trust badges (footer strip, optional)')
                ->collapsed()
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
