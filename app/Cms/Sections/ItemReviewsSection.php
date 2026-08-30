<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use Filament\Schemas\Components\Section;

/**
 * This product's or stack's approved reviews, as a section the operator can
 * add, position, and switch off.
 *
 * Reads the relation rather than holding content, exactly like
 * ItemFaqsSection — and here there was never a choice about it. Reviews are
 * moderated customer writing; a blueprint that let an operator type them into
 * a section would be a machine for fabricating testimonials. Only approved
 * ones are served, newest first.
 *
 * INTERIM BY DESIGN. The operator's plan is for reviews to come from a
 * third-party integration or a widget that syndicates out, and the
 * manually-entered ones are a stand-in until that lands. That changes where
 * the rows come from, not this section: it will still be the thing that says
 * whether reviews appear on a given record and where. Keep the section
 * presentation-only and the swap stays a backend job.
 *
 * `hasIntrinsicContent()` is true — its content is the relation, so it renders
 * with every field blank. The frontend renders nothing when a record has no
 * approved reviews, so adding this to a record without any is an absence, not
 * an empty heading. Catalog-only: no item on a CMS page.
 */
class ItemReviewsSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::ItemReviews;
    }

    public function label(): string
    {
        return 'This item’s reviews';
    }

    public function icon(): string
    {
        return 'heroicon-o-star';
    }

    public function description(): ?string
    {
        return 'Shows the approved reviews attached to this product or stack. Add it where you want them to appear; remove it and they do not show at all.';
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
            Section::make('Reviews')
                ->description('The reviews themselves are moderated under this record’s Reviews tab — only approved ones appear. Nothing here changes them.')
                ->components([
                    CopyFields::inline('heading')
                        ->label('Heading')
                        ->helperText('Optional. Leave blank for the site default.'),
                ]),
        ];
    }
}
