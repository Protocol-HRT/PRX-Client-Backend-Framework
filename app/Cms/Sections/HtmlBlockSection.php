<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * The escape hatch: markup an operator pastes, rendered verbatim.
 *
 * It exists because the rich-text fields cannot do this and should not try.
 * A rich editor treats pasted markup as TEXT — TipTap wraps each line in a
 * paragraph and escapes the angle brackets — so an operator pasting a custom
 * layout gets a page displaying its own source. That is not a rendering bug on
 * the frontend; the frontend is faithfully showing what got stored.
 *
 * **This field is injected without sanitisation, and that is the feature.**
 * The trust model is exactly the one `custom_css` and `custom_head_scripts`
 * already run under: permission-gated content, authored by this install's own
 * operators, from an admin only they can reach. Never route user-generated
 * content into it, and treat `Update:Page` as equivalent to script access —
 * because with this block, it is.
 *
 * Prefer a real section type when one fits. This is for the page that needs a
 * layout nothing else provides — a legal page with its own table of contents,
 * an embed a vendor hands you as a snippet — not a way around building a
 * blueprint for something the site does repeatedly.
 */
class HtmlBlockSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::HtmlBlock;
    }

    public function label(): string
    {
        return 'Custom HTML';
    }

    public function icon(): string
    {
        return 'heroicon-o-code-bracket';
    }

    public function description(): ?string
    {
        return 'Raw markup, rendered exactly as written. For layouts and embeds no other section provides — not a substitute for one that does.';
    }

    public function defaults(): array
    {
        return [
            'admin_label' => null,
            'html' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Custom HTML')
                ->description('Rendered exactly as written, with no filtering. Only paste markup you wrote or trust — anything in here runs on the public page.')
                ->components([
                    TextInput::make('admin_label')
                        ->label('Label (admin only)')
                        ->maxLength(80)
                        ->helperText('Names this block in the page builder list so a page with several is readable. Never shown publicly.'),
                    Textarea::make('html')
                        ->label('HTML')
                        ->rows(18)
                        ->required()
                        ->extraInputAttributes(['style' => 'font-family: ui-monospace, monospace; font-size: 13px;'])
                        ->helperText('Paste markup as source. Do not paste it into a rich-text field — those store it as text and the page ends up showing the tags.')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
