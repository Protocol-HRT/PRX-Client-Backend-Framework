<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class ResultsStatsSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::ResultsStats;
    }

    public function label(): string
    {
        return 'Results — by the numbers';
    }

    public function icon(): string
    {
        return 'heroicon-o-presentation-chart-line';
    }

    public function description(): ?string
    {
        return 'Dark-theme "By the Numbers" section: centered heading + 4-up grid of highlighted stat blocks (each with icon, gold value, white label, and sublabel).';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'By the Numbers',
            'heading' => 'The results speak',
            'emphasis' => 'for themselves.',
            'stats' => [
                [
                    'value' => '12,400+',
                    'label' => 'Patients Optimized',
                    'sublabel' => 'Active patients on protocol',
                    'icon' => 'patients',
                ],
                [
                    'value' => '38,600+',
                    'label' => 'Protocols Delivered',
                    'sublabel' => 'Physician-reviewed prescriptions',
                    'icon' => 'protocols',
                ],
                [
                    'value' => '50',
                    'label' => 'States Licensed',
                    'sublabel' => 'Nationwide telemedicine coverage',
                    'icon' => 'states',
                ],
                [
                    'value' => '97%',
                    'label' => 'Patient Satisfaction',
                    'sublabel' => 'Would recommend to a friend',
                    'icon' => 'satisfaction',
                ],
            ],
            'footer_note' => '* Data reflects [Brand Name] patient metrics as of '.now()->subYear()->year,
        ];
    }

    /**
     * Map of icon-key → inline SVG markup. Lives on the blueprint so the
     * Blade @php block stays minimal.
     *
     * @return array<string, string>
     */
    public function iconSvgMap(): array
    {
        return [
            'patients' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'protocols' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>',
            'states' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'satisfaction' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
            'pulse' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
            'shield' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
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
                        ->label('Heading accent (italic gold)')
                        ->maxLength(255)
                        ->helperText('Italic gold run rendered after the heading.'),
                ]),
            Repeater::make('stats')
                ->label('Stat blocks (4 typical)')
                ->schema([
                    TextInput::make('value')
                        ->required()
                        ->maxLength(60)
                        ->helperText('Pre-formatted value (e.g. "12,400+", "97%", "50").'),
                    TextInput::make('label')->required()->maxLength(120),
                    TextInput::make('sublabel')->maxLength(255),
                    Select::make('icon')
                        ->options([
                            'patients' => 'Patients (people group)',
                            'protocols' => 'Protocols (document)',
                            'states' => 'States (building)',
                            'satisfaction' => 'Satisfaction (star)',
                            'pulse' => 'Pulse (heartbeat)',
                            'shield' => 'Shield (protection)',
                        ])
                        ->default('patients')
                        ->native(false)
                        ->helperText('Built-in icon set rendered as inline SVG.'),
                ])
                ->columns(2)
                ->reorderable()
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
            Section::make('Footer note')
                ->components([
                    Textarea::make('footer_note')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Tiny mono note rendered below the stat grid.'),
                ]),
        ];
    }
}
