<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
        return 'Full-width hero slideshow: one or more slides (background image + heading + description + CTA each), with an optional floating highlight card. With no slides, the static headline fields render over the background image or video. The banner layout instead renders the static fields as a centered rounded banner (eyebrow, headline, subtext, CTA).';
    }

    public function defaults(): array
    {
        return [
            'layout' => 'slider',
            'slides' => [],
            'highlight_title' => null,
            'highlight_subtitle' => null,
            'highlight_quote' => null,
            'highlight_image' => null,
            'eyebrow' => null,
            'headline' => null,
            'headline_emphasis' => null,
            'subhead' => null,
            'primary_cta_label' => null,
            'primary_cta_url' => null,
            'secondary_cta_label' => null,
            'secondary_cta_url' => null,
            'trust_microcopy' => null,
            'background_image' => null,
            'background_video_url' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Select::make('layout')
                ->options([
                    'slider' => 'Slideshow (slides below)',
                    'banner' => 'Centered banner (static fields below)',
                ])
                ->default('slider')
                ->native(false)
                ->helperText('Centered banner ignores the slides and renders the static hero fields centered over the background image in a rounded frame.'),
            Repeater::make('slides')
                ->label('Slides')
                ->schema([
                    SectionImagePicker::make('image')->label('Background image'),
                    TextInput::make('image_alt')->label('Image alt text')->maxLength(255),
                    TextInput::make('heading')->required()->maxLength(255),
                    TextInput::make('heading_emphasis')
                        ->label('Heading accent (italic)')
                        ->maxLength(120)
                        ->helperText('Optional accent run rendered after the heading.'),
                    Textarea::make('description')->rows(2)->maxLength(500)->columnSpanFull(),
                    TextInput::make('cta_label')->label('CTA label')->maxLength(60),
                    TextInput::make('cta_url')->label('CTA URL')->maxLength(2048),
                    Select::make('text_theme')
                        ->label('Text tone')
                        ->options(['dark' => 'Dark text (light image)', 'light' => 'Light text (dark image)'])
                        ->default('dark')
                        ->native(false)
                        ->helperText('Pick the tone that stays readable over this slide\'s image.'),
                ])
                ->columns(2)
                ->reorderable()
                ->minItems(0)
                ->columnSpanFull()
                ->itemLabel(fn (array $state): ?string => $state['heading'] ?? null),
            Section::make('Highlight card')
                ->description('Optional floating card overlaid on the slideshow (product spotlight + short quote). Leave the title empty to hide it.')
                ->columns(2)
                ->components([
                    TextInput::make('highlight_title')->label('Title')->maxLength(120),
                    TextInput::make('highlight_subtitle')->label('Subtitle')->maxLength(120),
                    Textarea::make('highlight_quote')->label('Quote')->rows(2)->maxLength(255)->columnSpanFull(),
                    SectionImagePicker::make('highlight_image')->label('Image'),
                ]),
            Section::make('Static hero (no-slides fallback)')
                ->description('Rendered only when no slides are defined: headline + CTAs over the background image or video below.')
                ->collapsed()
                ->columns(2)
                ->components([
                    TextInput::make('eyebrow')
                        ->label('Eyebrow tag')
                        ->maxLength(120)
                        ->helperText('Small uppercase pill above the headline.')
                        ->columnSpanFull(),
                    TextInput::make('headline')->maxLength(255)->columnSpanFull(),
                    TextInput::make('headline_emphasis')
                        ->label('Headline accent (italic gold)')
                        ->maxLength(120)
                        ->helperText('Optional gold italic run rendered after the headline.')
                        ->columnSpanFull(),
                    Textarea::make('subhead')->rows(2)->maxLength(500)->columnSpanFull(),
                    TextInput::make('primary_cta_label')->maxLength(60),
                    TextInput::make('primary_cta_url')->maxLength(2048),
                    TextInput::make('secondary_cta_label')->maxLength(60),
                    TextInput::make('secondary_cta_url')->maxLength(2048),
                    TextInput::make('trust_microcopy')
                        ->label('Trust micro-copy under buttons')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    SectionImagePicker::make('background_image')->label('Background image'),
                    TextInput::make('background_video_url')
                        ->label('Background video embed URL')
                        ->url()
                        ->maxLength(2048)
                        ->helperText('Optional Vimeo/YouTube embed URL with autoplay/loop/mute parameters.'),
                ]),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return [
            'slides.*.image' => 'image',
            'highlight_image' => 'image',
            'background_image' => 'image',
        ];
    }
}
