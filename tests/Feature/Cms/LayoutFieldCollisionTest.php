<?php

namespace Tests\Feature\Cms;

use App\Cms\Support\LayoutFields;
use App\Cms\Support\SectionChildren;
use App\Filament\Resources\Cms\FlexibleSectionTypes\Schemas\FlexibleSectionTypeForm;
use App\Services\Cms\BlockRegistry;
use App\Services\Cms\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A layout/style knob key may never also be an authored field on a section
 * type. SectionFormBuilder injects the shared panels into EVERY type, so the
 * two share one flat `data` payload and a shared key silently becomes two
 * different things at once.
 *
 * This is not hypothetical. `background_image` was added to
 * LayoutFields::KEYS unprefixed and collided with the content field of the
 * same name on hero, cta-banner and image-callout-banner. The consequences
 * ran in both directions:
 *
 *   - presentationKeys() reclassified those sections' AUTHORED background
 *     images as presentation, so SectionContent::hasContent() stopped
 *     counting them. A hero carrying only a background image reports
 *     has_content: false and the frontend drops it off a live page.
 *   - the frontend painted the section's own content image a second time as
 *     a band background.
 *
 * Style words are precisely what a blueprint wants to name its own fields,
 * so the style knobs are namespaced. This test is what keeps them that way.
 */
class LayoutFieldCollisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_section_type_declares_a_field_named_like_a_layout_knob(): void
    {
        $collisions = [];

        foreach (app(SectionRegistry::class)->all() as $type => $definition) {
            $fieldKeys = array_merge(
                array_keys($definition->defaults()),
                array_map(
                    fn (string $path): string => explode('.', $path)[0],
                    array_keys($definition->fieldKinds()),
                ),
            );

            foreach (array_intersect($fieldKeys, LayoutFields::KEYS) as $key) {
                $collisions[] = "{$type}.{$key}";
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($collisions)),
            'These section types declare an authored field whose key is also a layout/style knob. '
            .'Rename the knob (style knobs carry a style_ prefix) or the field — they cannot share '
            .'one key in the flat `data` payload.'
        );
    }

    /**
     * The same rule one level down. A sub-block wears the identical Style and
     * Layout panels (SectionFormBuilder::blockFor), state-pathed into the
     * child's own `data`, so a block field named after a knob collides
     * exactly the way hero.background_image did — and with the same two
     * consequences, since a child gets its own presentationKeys() and its own
     * has_content verdict.
     */
    public function test_no_block_type_declares_a_field_named_like_a_layout_knob(): void
    {
        $collisions = [];

        foreach (app(BlockRegistry::class)->all() as $type => $blueprint) {
            $fieldKeys = array_merge(
                array_keys($blueprint->defaults()),
                array_map(
                    fn (string $path): string => explode('.', $path)[0],
                    array_keys($blueprint->fieldKinds()),
                ),
            );

            foreach (array_intersect($fieldKeys, LayoutFields::KEYS) as $key) {
                $collisions[] = "{$type}.{$key}";
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($collisions)),
            'These block types declare an authored field whose key is also a layout/style knob. '
            .'A block carries the same shared panels a section does, so the keys cannot be shared.'
        );
    }

    /**
     * `children` is structural, not authored. SectionDataTransformer
     * recognises it POSITIONALLY — anything stored there is run through the
     * block pipeline and a value that is not a list of `{type, data}` items
     * is dropped. A section type that declared a content field of that name
     * would therefore lose its content silently.
     *
     * It deliberately does NOT live in LayoutFields::KEYS: knobs there are
     * presentation and are skipped when deciding whether a section was
     * authored, and children ARE content. So this needs its own guard.
     */
    public function test_no_section_type_declares_a_field_named_children(): void
    {
        $collisions = [];

        foreach (app(SectionRegistry::class)->all() as $type => $definition) {
            // Both surfaces, not just fieldKinds(): a plain authored field
            // needs no field kind, so a Repeater keyed `children` with an
            // empty default would otherwise pass the guard and be swallowed.
            // Hero is the reason this is safe to assert — it deliberately
            // does not stamp the key into defaults().
            $declared = array_merge(
                array_keys($definition->defaults()),
                array_map(
                    fn (string $path): string => explode('.', $path)[0],
                    array_keys($definition->fieldKinds()),
                ),
            );

            if (in_array(SectionChildren::KEY, $declared, true)) {
                $collisions[] = "{$type}.".SectionChildren::KEY;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($collisions)),
            'These section types declare a field kind on the reserved `children` key. '
            .'Sub-blocks are stored there and are resolved structurally, so an authored '
            .'field of that name would be swallowed by the block pipeline.'
        );
    }

    /**
     * The runtime half of the same guard.
     *
     * LayoutFieldCollisionTest can only walk types that exist when it runs, so
     * it is blind to a flexible type an operator creates tomorrow. Those are
     * caught at the point the key is typed, and this asserts that list covers
     * every key the serve path resolves POSITIONALLY — the knobs, which the
     * form builder injects into every type, and `children`, which
     * SectionDataTransformer reads structurally and DELETES when it does not
     * hold typed block items.
     *
     * Without the `children` half, an operator could key a flexible field
     * `children`, author into it, and watch the content vanish on publish —
     * the `background_image` collision over again, which shipped both a test
     * and this validation precisely because one without the other is a hole.
     */
    public function test_the_flexible_type_form_reserves_every_structurally_resolved_key(): void
    {
        $reserved = FlexibleSectionTypeForm::reservedFieldKeys();

        foreach (LayoutFields::KEYS as $key) {
            $this->assertContains($key, $reserved, "Layout knob '{$key}' is not refused as a flexible field key.");
        }

        // A RETIRED knob is still read positionally, so it is still reserved.
        // Dropping a key from KEYS unreserves the NAME while RETIRED_KEYS keeps
        // it classified as presentation — so without this, an operator could
        // key a flexible field after a retired knob, author into it, and have
        // hasContent() skip it and drop the whole section. Found in review, one
        // commit after the first retirement made it possible.
        foreach (LayoutFields::RETIRED_KEYS as $key) {
            $this->assertContains(
                $key,
                $reserved,
                "Retired layout knob '{$key}' is not refused as a flexible field key. It is still "
                .'skipped by hasContent(), so a field of that name would have its content dropped.'
            );
        }

        $this->assertContains(
            SectionChildren::KEY,
            $reserved,
            'The reserved `children` key is not refused as a flexible field key — an admin-defined '
            .'type could author into it and have the content dropped on serve.'
        );
    }
}
