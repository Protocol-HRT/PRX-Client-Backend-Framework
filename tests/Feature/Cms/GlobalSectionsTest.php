<?php

namespace Tests\Feature\Cms;

use App\Actions\Cms\DeleteGlobalSectionAction;
use App\Actions\Cms\DetachGlobalSectionAction;
use App\Models\Cms\GlobalSection;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class GlobalSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function referencedSection(GlobalSection $global, string $slug = 'global-page'): PageSection
    {
        $page = Page::factory()->create(['slug' => $slug]);

        return PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => $global->type,
            'data' => null,
            'global_section_id' => $global->id,
        ]);
    }

    // ─── API resolution ───────────────────────────────────────────────

    public function test_referenced_global_supplies_type_data_and_envelope_metadata(): void
    {
        $global = GlobalSection::factory()->create([
            'name' => 'Footer CTA',
            'slug' => 'footer-cta',
            'data' => ['heading' => 'Ready to start?', 'primary_cta_label' => 'Go'],
        ]);
        $this->referencedSection($global);

        $section = $this->getJson('/api/v1/pages/global-page')->json('data.sections.0');

        $this->assertSame('cta-banner', $section['type']);
        $this->assertSame('Ready to start?', $section['data']['heading']);
        $this->assertSame('footer-cta', $section['global']['slug']);
        $this->assertSame('Footer CTA', $section['global']['name']);
    }

    public function test_editing_global_updates_every_referencing_page_immediately(): void
    {
        $global = GlobalSection::factory()->create(['data' => ['heading' => 'Before']]);
        $this->referencedSection($global, 'page-one');
        $this->referencedSection($global, 'page-two');

        $this->assertSame('Before', $this->getJson('/api/v1/pages/page-one')->json('data.sections.0.data.heading'));

        $global->update(['data' => ['heading' => 'After']]);

        $this->assertSame('After', $this->getJson('/api/v1/pages/page-one')->json('data.sections.0.data.heading'));
        $this->assertSame('After', $this->getJson('/api/v1/pages/page-two')->json('data.sections.0.data.heading'));
    }

    public function test_disabled_global_is_skipped_wherever_referenced(): void
    {
        $global = GlobalSection::factory()->disabled()->create();
        $section = $this->referencedSection($global);
        PageSection::factory()->hero()->create(['page_id' => $section->page_id]);

        $sections = $this->getJson('/api/v1/pages/global-page')->json('data.sections');

        $this->assertCount(1, $sections);
        $this->assertSame('hero', $sections[0]['type']);
    }

    public function test_global_data_flows_through_field_kind_transformation(): void
    {
        $global = GlobalSection::factory()->create([
            'type' => 'image-text-split',
            'data' => ['heading' => 'Split', 'image' => 'legacy/pic.jpg'],
        ]);
        $this->referencedSection($global);

        $image = $this->getJson('/api/v1/pages/global-page')->json('data.sections.0.data.image');

        $this->assertNull($image['id']);
        $this->assertStringContainsString('legacy/pic.jpg', $image['url']);
    }

    // ─── Actions ──────────────────────────────────────────────────────

    public function test_detach_copies_content_and_stops_following_the_global(): void
    {
        $global = GlobalSection::factory()->create(['data' => ['heading' => 'Original']]);
        $section = $this->referencedSection($global);

        app(DetachGlobalSectionAction::class)->execute($section);

        $section->refresh();
        $this->assertNull($section->global_section_id);
        $this->assertSame('Original', $section->data['heading']);

        $global->update(['data' => ['heading' => 'Changed later']]);

        $payload = $this->getJson('/api/v1/pages/global-page')->json('data.sections.0');
        $this->assertSame('Original', $payload['data']['heading']);
        $this->assertNull($payload['global']);
    }

    public function test_delete_blocked_while_referenced(): void
    {
        $global = GlobalSection::factory()->create();
        $this->referencedSection($global);

        $this->expectException(RuntimeException::class);

        app(DeleteGlobalSectionAction::class)->execute($global);
    }

    public function test_delete_allowed_when_unreferenced(): void
    {
        $global = GlobalSection::factory()->create();

        app(DeleteGlobalSectionAction::class)->execute($global);

        $this->assertDatabaseMissing('global_sections', ['id' => $global->id]);
    }
}
