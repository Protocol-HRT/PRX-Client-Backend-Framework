<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use Filament\Schemas\Components\Section;

/**
 * This product's or stack's own FAQs, as a section the operator can add,
 * position, and switch off.
 *
 * IT READS THE RELATION; IT DOES NOT HOLD THE CONTENT. The questions stay
 * where they are authored — the FAQs relation manager on the record — and
 * this section decides only whether they appear, where, and under what
 * heading. That was the operator's explicit choice, and it is the reason the
 * existing `faq` blueprint could not simply be reused: that one carries its
 * own question repeater, so adopting it would have meant retyping every FAQ
 * already attached to a record and leaving two places to edit one answer.
 *
 * Consequently `hasIntrinsicContent()` is true — its content is the relation,
 * not the copy above it, so it must render with every field blank. Same
 * category as QuizSection. The frontend still renders nothing when the record
 * has no published FAQs, so an operator who adds this to a record without any
 * gets an absence rather than an empty heading.
 *
 * Catalog-only: there is no item to read on a CMS page.
 *
 * Before this existed, FAQs rendered on every detail page purely because the
 * record had some — no toggle, no position, no way to leave them off. The
 * operator's summary of the whole class of problem: "Data does not = display
 * always!"
 */
class ItemFaqsSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::ItemFaqs;
    }

    public function label(): string
    {
        return 'This item’s FAQs';
    }

    public function icon(): string
    {
        return 'heroicon-o-question-mark-circle';
    }

    public function description(): ?string
    {
        return 'Shows the FAQs attached to this product or stack, under the FAQs tab. Add it where you want them to appear; remove it and they do not show at all.';
    }

    public function contexts(): array
    {
        return ['catalog'];
    }

    public function hasIntrinsicContent(): bool
    {
        return true;
    }

    public function defaults(): array
    {
        return [
            'heading' => null,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('FAQs')
                ->description('The questions themselves come from this record’s FAQs tab. Nothing here changes them — this only controls whether and where they appear on the page.')
                ->components([
                    CopyFields::inline('heading')
                        ->label('Heading')
                        ->helperText('Optional. Leave blank for the site default.'),
                ]),
        ];
    }
}
