<?php

namespace Tests\Feature\Cms;

use App\Cms\Support\LayoutFields;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Every section envelope carries `has_content`, so a consuming frontend can
 * honour "a section with no authored content renders nothing" without
 * reimplementing the walk — or guessing which keys are structural flags.
 */
class SectionHasContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function sectionEnvelope(array $data, string $type = 'text-block'): array
    {
        $page = Page::factory()->create(['slug' => 'content-flag']);
        PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => $type,
            'data' => $data,
        ]);

        return $this->getJson('/api/v1/pages/content-flag')
            ->assertOk()
            ->json('data.sections.0');
    }

    /**
     * A FUNCTIONAL section renders on its own, so an empty payload is not an
     * empty section.
     *
     * The quiz section mounts the intake wizard; the copy above it is
     * optional decoration. Judged by authored content alone it would report
     * has_content: false and be dropped, and an operator who added it and
     * wrote no heading would watch it silently vanish — the least debuggable
     * failure this CMS has. `hasIntrinsicContent()` is the explicit claim
     * that the component renders something without help.
     */
    public function test_a_functional_section_reports_content_with_an_empty_payload(): void
    {
        $envelope = $this->sectionEnvelope([
            'eyebrow' => null,
            'heading' => null,
            'heading_level' => 'h2',
            'body' => null,
            'goals' => [],
        ], 'quiz');

        $this->assertTrue($envelope['has_content']);
    }

    /**
     * The flag must not be a blanket exemption. An editorial section with the
     * same empty payload still reports nothing, which is what stops an empty
     * scaffold reaching a live page.
     */
    public function test_the_intrinsic_flag_does_not_leak_to_editorial_sections(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
        ], 'text-block');

        $this->assertFalse($envelope['has_content']);
    }

    public function test_an_authored_section_reports_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => 'Privacy Policy',
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
        ]);

        $this->assertTrue($envelope['has_content']);
    }

    /**
     * The regression this whole change exists for: an editor adds a section
     * and never fills it in. Its defaults ship `theme` and `alignment`, which
     * used to be enough to make the payload look authored.
     */
    public function test_an_untouched_scaffold_reports_no_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
        ]);

        $this->assertFalse($envelope['has_content']);
    }

    public function test_setting_only_a_layout_knob_is_not_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'dark',
            'alignment' => 'center',
            'content_width' => 'narrow',
            'style_padding_top' => 'lg',
        ]);

        $this->assertFalse($envelope['has_content']);
    }

    public function test_body_alone_is_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => '<p>Real copy.</p>',
            'theme' => 'light',
            'alignment' => 'left',
        ]);

        $this->assertTrue($envelope['has_content']);
    }

    public function test_setting_only_media_width_is_not_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
            'media_width' => 'full',
        ]);

        $this->assertFalse($envelope['has_content']);
    }

    /**
     * The type's design default is merged into the payload before has_content
     * is computed, so the merge running must not be able to resurrect an empty
     * scaffold. This asserts the ORDER, which the unit tests cannot see.
     */
    public function test_a_merged_design_default_does_not_make_a_scaffold_look_authored(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
        ]);

        $this->assertSame('wide', $envelope['data']['content_width']);
        $this->assertFalse($envelope['has_content']);
    }

    public function test_a_section_is_served_its_types_design_default(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => 'Privacy Policy',
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
        ]);

        $this->assertSame('wide', $envelope['data']['content_width']);
    }

    /**
     * media_width has its own default on the types where containment was
     * previously hardcoded in the frontend; assert the second knob merges
     * too, not just content_width.
     */
    public function test_a_type_with_a_media_default_is_served_it(): void
    {
        $envelope = $this->sectionEnvelope(['heading' => 'Longevity, engineered'], 'hero');

        $this->assertSame('contained', $envelope['data']['media_width']);
        $this->assertSame('full', $envelope['data']['content_width']);
    }

    public function test_an_operator_width_survives_the_merge(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => 'Privacy Policy',
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
            'content_width' => 'narrow',
        ]);

        $this->assertSame('narrow', $envelope['data']['content_width']);
    }

    /**
     * THE STAGE-2 FALSIFIER, written before the fields existed.
     *
     * Per-breakpoint overrides are stored as flat suffixed keys
     * (`content_align_md`) rather than nested under the base key, and this is
     * the test that says the shape was right. SectionContent::hasContent()
     * skips presentation keys BY NAME, never by value shape, so a suffixed
     * key is presentation for exactly one reason: it is listed in
     * LayoutFields::KEYS. One line per key, no classifier change.
     *
     * The brief was explicit that if making this pass took more than that,
     * the payload shape was wrong and the work should stop and re-scope. It
     * did not — which is the evidence for flat-over-nested, since a nested
     * shape would have needed hasContent() to learn to walk INTO a value it
     * currently only name-checks.
     *
     * A section holding nothing but overrides is an untouched scaffold whose
     * operator nudged a tablet alignment. It must still vanish from a live
     * page.
     */
    public function test_setting_only_responsive_overrides_is_not_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
            'content_align_md' => 'center',
            'content_align_lg' => 'right',
            'content_inset_md' => 'md',
            'content_inset_lg' => 'lg',
            'style_padding_top_md' => 'lg',
            'style_padding_bottom_lg' => 'sm',
        ]);

        $this->assertFalse($envelope['has_content']);
    }

    /**
     * The stage-1-pass-2 half of the same guard: the frame knobs that replace
     * `extra_padding` and add border/radius are presentation too.
     *
     * `style_border_color` is the one that also has to reach PaletteUsage::KEYS
     * in this same commit — it stores a palette NAME, so deleting that colour
     * must be blocked exactly as it is for a background. The subset relation is
     * pinned separately by PaletteDeletionGuardTest.
     */
    public function test_setting_only_frame_knobs_is_not_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
            'style_padding_top' => 'lg',
            'style_padding_bottom' => 'sm',
            'style_border_color' => 'ink',
            'style_border_width' => 'md',
            'style_radius' => 'lg',
        ]);

        $this->assertFalse($envelope['has_content']);
    }

    /**
     * `flush` is a categorical override, not a size — it counter-bleeds the
     * page gutter so content reaches the viewport edge. It is still a knob,
     * and an operator who set only that authored nothing.
     */
    public function test_setting_only_a_flush_inset_is_not_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
            'content_inset' => 'flush',
            'content_inset_md' => 'md',
        ]);

        $this->assertFalse($envelope['has_content']);
    }

    /**
     * A RETIRED knob's stored value is still presentation, not copy.
     *
     * Found by measurement, not by reasoning: retiring `extra_padding` left it
     * in 13 rows of stored JSON, and dropping it from LayoutFields::KEYS made
     * hasContent() count it as authored. One real section — the deliberately
     * empty scaffold on the /test-page bench — flipped false to true, meaning
     * an empty band would have rendered on a live page.
     *
     * The section below is an untouched scaffold whose operator once nudged
     * the padding. It has no copy. It must not render.
     */
    public function test_a_retired_knobs_stored_value_is_still_not_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'heading' => null,
            'body' => null,
            'theme' => 'light',
            'alignment' => 'left',
            'extra_padding' => 'lg',
        ]);

        $this->assertFalse(
            $envelope['has_content'],
            'A retired layout knob left in stored data is being counted as authored content, '
            .'so an empty scaffold section will render onto a live page. Add the key to '
            .'LayoutFields::RETIRED_KEYS when its control is removed.'
        );
    }

    /**
     * The rule behind the test above, stated so a future retirement inherits
     * it: a retired key may never quietly become a live key again, and the two
     * lists may never overlap — an entry in both is a contradiction about
     * whether the control exists.
     */
    public function test_retired_and_live_knob_vocabularies_do_not_overlap(): void
    {
        $this->assertSame(
            [],
            array_values(array_intersect(
                LayoutFields::KEYS,
                LayoutFields::RETIRED_KEYS,
            )),
            'A key is listed as both a live knob and a retired one.'
        );

        $this->assertNotContains(
            'extra_padding',
            LayoutFields::KEYS,
            'extra_padding was replaced by style_padding_top / style_padding_bottom.'
        );
    }

    /**
     * Child positioning classifies itself, with no shared-vocabulary entry.
     *
     * `highlight_position` is a HERO BLUEPRINT FIELD, not a member of
     * LayoutFields::KEYS — positioning a child means something only where the
     * parent owns a slot to position it in, and the hero is the only section
     * that does. It stays out of has_content's way purely by carrying a
     * non-null default, which DeclaresPresentationKeys unions in.
     *
     * So this test is really asserting the MECHANISM: change the default to
     * null and an untouched hero starts reporting has_content: true, and an
     * empty slideshow reaches a live page.
     */
    public function test_the_hero_position_field_is_presentation_not_content(): void
    {
        $envelope = $this->sectionEnvelope([
            'layout' => 'slider',
            'slides' => [],
            'headline' => null,
            'highlight_position' => 'bottom-left',
        ], 'hero');

        $this->assertFalse(
            $envelope['has_content'],
            'A hero holding nothing but a highlight position is being counted as authored. '
            .'The field classifies itself only while its default is non-null.'
        );
    }

    /**
     * The default classifies the key; it does NOT reach the payload.
     *
     * Worth stating because it is easy to assume otherwise: only
     * LayoutFields::applyDefaults() merges anything into served data, and it
     * merges LayoutDefaults — the shared knobs — not a blueprint's own
     * defaults(). A blueprint default drives the FORM and the presentation
     * classification, so a hero saved through the admin carries the key while
     * one written by a fill script does not.
     *
     * The frontend therefore owns the fallback, and must treat an absent or
     * unrecognised value as `middle-right` — today's hardcoded placement — so
     * a hero authored before this field existed renders exactly as it did.
     */
    public function test_the_position_default_classifies_without_being_served(): void
    {
        $definition = app(SectionRegistry::class)->resolve('hero');

        $this->assertContains(
            'highlight_position',
            $definition->presentationKeys(),
            'The position field is not classified as presentation — its default must stay non-null.'
        );

        $envelope = $this->sectionEnvelope(['headline' => 'Longevity, engineered'], 'hero');

        $this->assertArrayNotHasKey(
            'highlight_position',
            $envelope['data'],
            'A blueprint default is not merged into served data. If this starts passing the '
            .'merge behaviour changed, and the frontend fallback may no longer be reachable.'
        );
    }
}
