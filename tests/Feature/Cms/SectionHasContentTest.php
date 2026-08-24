<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\PageSection;
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
            'extra_padding' => 'lg',
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
}
