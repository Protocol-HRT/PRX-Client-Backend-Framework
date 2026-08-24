<?php

namespace Tests\Feature\Cms;

use App\Cms\Support\LayoutFields;
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
}
