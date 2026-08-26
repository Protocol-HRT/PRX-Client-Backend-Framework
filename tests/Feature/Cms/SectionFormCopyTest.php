<?php

namespace Tests\Feature\Cms;

use App\Filament\Support\SectionFormBuilder;
use App\Services\Cms\BlockRegistry;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Tests\TestCase;

/**
 * The shared knob panels must BUILD at both levels, and their hints must have
 * something in them.
 *
 * Everything else about these panels is asserted as data. Nothing asserted
 * that the form an operator opens can be constructed at all — which is why
 * "somebody should render the section edit form" sat in the handoff across
 * several sessions as an item no code-level check could close. This closes
 * the cheap half of it: construction and copy. It is not a substitute for
 * looking at the screen, and does not claim to be.
 *
 * It matters because the panels are built TWICE from one definition — once
 * for a section and once for a typed child block — and the copy now branches
 * on which. A `$nested` mistake is invisible until someone opens the other
 * one.
 *
 * Asserts that hints ARRIVE, never what they say: hint wording should be
 * editable without a test to update. The two exceptions are pinned by intent
 * rather than phrasing, and both are recorded misunderstandings rather than
 * style preferences.
 */
class SectionFormCopyTest extends TestCase
{
    public function test_both_panels_build_at_both_levels(): void
    {
        foreach ([false, true] as $nested) {
            $fields = $this->fieldsOf($nested);

            $this->assertNotEmpty(
                $fields,
                'no fields built (nested: '.var_export($nested, true).')'
            );
        }
    }

    public function test_every_hint_icon_carries_text(): void
    {
        foreach ([false, true] as $nested) {
            $hinted = 0;

            foreach ($this->fieldsOf($nested) as $name => $field) {
                if ($field->getHintIcon() === null) {
                    continue;
                }

                $hinted++;

                $this->assertNotSame(
                    '',
                    trim((string) $field->getHintIconTooltip()),
                    "{$name} has a hint icon with no text — an operator gets an icon that says nothing"
                );
            }

            // A hint vanishing silently is the regression this guards against;
            // the exact number is free to grow.
            $this->assertGreaterThanOrEqual(
                6,
                $hinted,
                'far fewer hints than expected (nested: '.var_export($nested, true).')'
            );
        }
    }

    /**
     * Controls whose BEHAVIOUR differs between a section and a block must not
     * describe themselves identically.
     *
     * This is the defect this pass shipped and a review caught: interpolating
     * a "section"/"block" noun into one shared string reads as level-aware
     * while still asserting section physics one level down. A block's radius
     * changes its corners and nothing else — `.sxb-radius--*` sets
     * `border-radius` alone — so copy telling a block operator that "Square
     * keeps it full width" describes a distinction that does not exist there.
     *
     * The general rule a noun swap cannot satisfy: where the two levels differ
     * mechanically, the sentence has to be written twice.
     */
    public function test_copy_differs_where_the_two_levels_behave_differently(): void
    {
        $section = $this->fieldsOf(false);
        $block = $this->fieldsOf(true);

        foreach ([
            // `flush` exists on one and not the other.
            'content_inset',
            // A section's radius cancels its own bleed and narrows the band;
            // a block has no bleed, so its width never changes.
            'style_radius',
            // The reason there is no left/right padding is a screen-edge
            // consequence that only a full-bleed section can suffer.
            'style_padding_bottom',
        ] as $name) {
            $sectionCopy = trim((string) $section[$name]->getHintIconTooltip());
            $blockCopy = trim((string) $block[$name]->getHintIconTooltip());

            $this->assertNotSame($sectionCopy, $blockCopy, "{$name} explains itself identically at both levels");

            // The assertion that matters, and the one a naive "are they
            // different" check misses: swapping the noun DOES produce two
            // different strings, which is exactly how the bug shipped. Undo
            // the swap and they must still differ — otherwise the only thing
            // separating the two explanations is a word, and the mechanics
            // being described are the section's at both levels.
            $this->assertNotSame(
                $sectionCopy,
                str_replace(['block', 'Block'], ['section', 'Section'], $blockCopy),
                "{$name}'s block copy is its section copy with the noun swapped. These controls behave differently at the two levels, so the explanation has to be written twice, not renamed"
            );
        }
    }

    /**
     * `none` is LABELLED "Square" on the radius control, because "None" reads
     * as "no setting" rather than as the value it is — and the help text used
     * to tell operators to pick a "Square" option that did not exist.
     *
     * The stored value is still `none`. If the key ever changes, the label is
     * lying about the data and every stored radius is orphaned.
     */
    public function test_square_is_a_label_for_the_stored_none_radius(): void
    {
        $radius = $this->fieldsOf(false)['style_radius'] ?? null;

        $this->assertNotNull($radius, 'the radius control is gone');
        $this->assertSame('Square', $radius->getOptions()['none'] ?? null);
        $this->assertArrayNotHasKey(
            'square',
            $radius->getOptions(),
            'a `square` KEY would be a second token meaning what `none` already means'
        );
    }

    /**
     * `flush` cancels the page gutter, and a child block is not adjacent to
     * the page edge — so the option must exist on a section and not on a
     * block. This is the one place the two levels deliberately differ.
     */
    public function test_flush_is_offered_on_a_section_and_withheld_from_a_block(): void
    {
        $this->assertArrayHasKey('flush', $this->fieldsOf(false)['content_inset']->getOptions());
        $this->assertArrayNotHasKey('flush', $this->fieldsOf(true)['content_inset']->getOptions());

        // ...including in the per-breakpoint overrides, which build their
        // options from the same field.
        $this->assertArrayNotHasKey('flush', $this->fieldsOf(true)['content_inset_md']->getOptions());
    }

    public function test_every_registered_block_builds_its_form(): void
    {
        $blocks = app(BlockRegistry::class)->builderBlocks();

        $this->assertNotEmpty($blocks, 'no block types registered');

        foreach ($blocks as $block) {
            $found = [];
            $this->collect($block, $found);

            $this->assertNotEmpty($found, 'block built no fields: '.$block->getName());
        }
    }

    /**
     * @return array<string, Field>
     */
    private function fieldsOf(bool $nested): array
    {
        $found = [];

        $this->collect(SectionFormBuilder::styleSection($nested), $found);
        $this->collect(SectionFormBuilder::layoutSection($nested), $found);

        return $found;
    }

    /**
     * @param  array<string, Field>  $found
     */
    private function collect(Component $component, array &$found): void
    {
        foreach ($component->getDefaultChildComponents() as $child) {
            if ($child instanceof Field) {
                $found[$child->getName()] = $child;
            }

            if ($child instanceof Component) {
                $this->collect($child, $found);
            }
        }
    }
}
