<?php

namespace App\Cms\Blocks;

use App\Cms\Support\CopyFields;
use App\Filament\Support\SectionImagePicker;

/**
 * A single testimonial / product-spotlight card, authored as a child of a
 * section rather than as flat fields on one.
 *
 * This is the case that motivated sub-blocks. The hero used to carry exactly
 * one of these as four singular fields (highlight_title / highlight_subtitle
 * / highlight_quote / highlight_image), so there was no way to hold a second
 * one, position it, or give it slider behaviour. As a repeatable typed child
 * it gets all three for free, and the same block is reusable by any section
 * that wants a card.
 *
 * The flat hero fields are still readable — HeroSection::resolveData()
 * synthesizes a one-item child from them when no children are authored — so
 * nothing needed migrating in the database.
 */
class TestimonialBlock extends BlockBlueprint
{
    public function type(): string
    {
        return 'testimonial';
    }

    public function label(): string
    {
        return 'Testimonial card';
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-bottom-center-text';
    }

    public function description(): ?string
    {
        return 'A small card with an image, a label, an audience line and a short quote. Add several to turn the card into a mini slider.';
    }

    public function defaults(): array
    {
        return [
            'title' => null,
            'subtitle' => null,
            'quote' => null,
            'image' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            CopyFields::inline('title')
                ->label('Title')
                ->helperText('The bold line at the top of the card — usually a product or programme name.'),
            CopyFields::inline('subtitle')
                ->label('Subtitle')
                ->helperText('Smaller line under the title, e.g. who it is for.'),
            CopyFields::inline('quote')
                ->label('Quote')
                ->columnSpanFull(),
            SectionImagePicker::make('image')
                ->label('Image')
                ->columnSpanFull(),
        ];
    }

    /** @return array<string, string> */
    public function fieldKinds(): array
    {
        return [
            'image' => 'image',
        ];
    }
}
