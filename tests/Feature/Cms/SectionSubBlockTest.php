<?php

namespace Tests\Feature\Cms;

use App\Services\Cms\SectionDataTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sub-block pipeline: a section's `data` may hold a repeater of TYPED
 * children, each carrying its own knobs and its own emptiness verdict.
 *
 * The case worth pinning hardest is the third one. Before children existed,
 * SectionContent judged a section by walking its top-level values, and a
 * child arrives as `{type, data, has_content}` whose `type` is always a
 * non-empty string. A generic walk therefore counts ANY child as authored
 * content — including one holding nothing but a background colour — and an
 * empty scaffold hero would report has_content: true and reach a live page.
 * That is the exact regression SectionContent was written to prevent,
 * reproduced one level down.
 */
class SectionSubBlockTest extends TestCase
{
    use RefreshDatabase;

    private function envelope(array $data): array
    {
        return app(SectionDataTransformer::class)->envelopeFor('hero', $data);
    }

    private function scaffold(array $children): array
    {
        return ['layout' => 'slider', 'slides' => [], 'children' => $children];
    }

    public function test_a_typed_child_is_served_as_a_block_envelope(): void
    {
        $envelope = $this->envelope($this->scaffold([
            ['type' => 'testimonial', 'data' => ['quote' => '<p>Worth it.</p>']],
        ]));

        $child = $envelope['data']['children'][0];

        $this->assertSame('testimonial', $child['type']);
        $this->assertSame('<p>Worth it.</p>', $child['data']['quote']);
        $this->assertTrue($child['has_content']);
    }

    public function test_a_child_receives_its_own_block_layout_defaults(): void
    {
        $envelope = $this->envelope($this->scaffold([
            ['type' => 'testimonial', 'data' => ['quote' => '<p>x</p>']],
        ]));

        // From BlockDefaults, which is keyed by BLOCK slug — not from
        // LayoutDefaults, whose `hero` row belongs to the parent.
        $this->assertSame('full', $envelope['data']['children'][0]['data']['content_width']);
    }

    public function test_a_child_holding_only_knobs_does_not_make_the_section_authored(): void
    {
        $envelope = $this->envelope($this->scaffold([
            ['type' => 'testimonial', 'data' => ['style_background_color' => 'sand']],
        ]));

        $this->assertFalse($envelope['data']['children'][0]['has_content']);
        $this->assertFalse(
            $envelope['has_content'],
            'An empty scaffold whose only child is empty must not reach a live page.'
        );
    }

    public function test_an_authored_child_alone_makes_the_section_authored(): void
    {
        $envelope = $this->envelope($this->scaffold([
            ['type' => 'testimonial', 'data' => ['style_background_color' => 'sand']],
            ['type' => 'testimonial', 'data' => ['title' => 'Performance Stack']],
        ]));

        $this->assertTrue($envelope['has_content']);
    }

    public function test_a_child_whose_block_type_no_longer_resolves_is_dropped(): void
    {
        $envelope = $this->envelope($this->scaffold([
            ['type' => 'retired-block', 'data' => ['quote' => '<p>orphan</p>']],
        ]));

        $this->assertArrayNotHasKey('children', $envelope['data']);
        $this->assertFalse($envelope['has_content']);
    }

    public function test_an_item_with_no_type_is_dropped(): void
    {
        // Filament writes a partially-added block before its type is chosen.
        $envelope = $this->envelope($this->scaffold([
            ['data' => ['quote' => '<p>half-added</p>']],
            'not-an-array',
        ]));

        $this->assertArrayNotHasKey('children', $envelope['data']);
    }

    public function test_a_section_with_no_children_does_not_carry_the_key(): void
    {
        $envelope = app(SectionDataTransformer::class)->envelopeFor('text-block', ['body' => '<p>x</p>']);

        $this->assertArrayNotHasKey(
            'children',
            $envelope['data'],
            'A section nobody added children to must serve exactly what it served before sub-blocks existed.'
        );
    }

    public function test_the_hero_reads_its_legacy_highlight_fields_as_one_child(): void
    {
        $envelope = $this->envelope([
            'layout' => 'slider',
            'slides' => [],
            'highlight_title' => 'Performance Stack',
            'highlight_subtitle' => 'For active women',
            'highlight_quote' => '<p>Finally a protocol designed around my hormones.</p>',
        ]);

        $children = $envelope['data']['children'];

        $this->assertCount(1, $children);
        $this->assertSame('testimonial', $children[0]['type']);
        $this->assertSame('Performance Stack', $children[0]['data']['title']);
        $this->assertTrue($children[0]['has_content']);
    }

    public function test_authored_children_supersede_the_legacy_highlight_fields(): void
    {
        $envelope = $this->envelope([
            'layout' => 'slider',
            'slides' => [],
            'highlight_title' => 'Old card',
            'children' => [
                ['type' => 'testimonial', 'data' => ['title' => 'New card']],
            ],
        ]);

        $children = $envelope['data']['children'];

        $this->assertCount(1, $children);
        $this->assertSame('New card', $children[0]['data']['title']);
    }

    public function test_empty_legacy_highlight_fields_synthesize_nothing(): void
    {
        $envelope = $this->envelope([
            'layout' => 'slider',
            'slides' => [],
            'highlight_title' => null,
            'highlight_quote' => null,
        ]);

        $this->assertArrayNotHasKey('children', $envelope['data']);
        $this->assertFalse($envelope['has_content']);
    }
}
