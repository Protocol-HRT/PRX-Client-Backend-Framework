<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;

class HeroSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Hero;
    }

    public function label(): string
    {
        return 'Hero';
    }

    public function icon(): string
    {
        return 'heroicon-o-star';
    }

    public function description(): ?string
    {
        return 'Two-column hero with optional Vimeo background video. Sticky pitch + AI concierge chat widget on the right.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => 'Trusted by 10,000+ patients across all 50 states',
            'headline' => 'Get a Personalized Hormone and Peptide Protocol for $49',
            'headline_emphasis' => null,
            'subhead' => 'Reviewed by physicians, powered by AI, and fully credited toward your treatment plan',
            'primary_cta_label' => 'Start My Protocol — $49',
            'primary_cta_url' => '#pricing',
            'secondary_cta_label' => 'See How It Works',
            'secondary_cta_url' => '#how-it-works',
            'trust_microcopy' => 'Results guaranteed or we make it right · Cancel anytime',
            'concierge_eyebrow' => 'AI Protocol Concierge',
            'concierge_link_label' => 'Find your protocol in minutes →',
            'concierge_link_url' => '#concierge',
            'background_video_url' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Headline')
                ->components([
                    TextInput::make('eyebrow')
                        ->label('Eyebrow tag')
                        ->maxLength(120)
                        ->helperText('Small uppercase pill above the headline.')
                        ->columnSpanFull(),
                    TextInput::make('headline')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('headline_emphasis')
                        ->label('Headline accent (italic gold)')
                        ->maxLength(120)
                        ->helperText('Optional gold italic run rendered after the headline (e.g. "$49").')
                        ->columnSpanFull(),
                    Textarea::make('subhead')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
            Section::make('Calls to action')
                ->columns(2)
                ->components([
                    TextInput::make('primary_cta_label')->maxLength(60),
                    TextInput::make('primary_cta_url')->maxLength(2048),
                    TextInput::make('secondary_cta_label')->maxLength(60),
                    TextInput::make('secondary_cta_url')->maxLength(2048),
                    TextInput::make('trust_microcopy')
                        ->label('Trust micro-copy under buttons')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            Section::make('AI concierge label')
                ->description('The small inline label that bridges the pitch column to the chat widget on the right.')
                ->columns(2)
                ->components([
                    TextInput::make('concierge_eyebrow')->label('Eyebrow')->maxLength(60),
                    TextInput::make('concierge_link_label')->label('Link label')->maxLength(120),
                    TextInput::make('concierge_link_url')->label('Link URL')->maxLength(2048),
                ]),
            Section::make('Background video')
                ->description('Optional. Paste a Vimeo or YouTube embed URL with autoplay/loop/mute parameters. Leave blank to skip the video.')
                ->components([
                    TextInput::make('background_video_url')
                        ->label('Embed URL')
                        ->url()
                        ->maxLength(2048),
                ]),
        ];
    }
}
