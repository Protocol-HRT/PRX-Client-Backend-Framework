<?php

namespace Tests\Feature\Cms;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\RelationManagers\SectionsRelationManager;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SectionsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function manager(Page $page)
    {
        return Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditPage::class,
        ]);
    }

    public function test_edit_modal_hydrates_the_section_type(): void
    {
        $page = Page::factory()->create();
        $section = PageSection::factory()->hero()->create(['page_id' => $page->id]);

        $this->manager($page)
            ->mountTableAction('edit', $section)
            ->assertSchemaStateSet([
                'type' => 'hero',
                'enabled' => true,
            ]);
    }

    public function test_editing_a_section_saves_without_type_errors(): void
    {
        $page = Page::factory()->create();
        $section = PageSection::factory()->hero()->create(['page_id' => $page->id]);

        $this->manager($page)
            ->callTableAction('edit', $section, data: [
                'enabled' => false,
                'anchor_id' => 'hero-anchor',
            ])
            ->assertHasNoTableActionErrors();

        $section->refresh();
        $this->assertFalse($section->enabled);
        $this->assertSame('hero-anchor', $section->anchor_id);
        $this->assertSame('hero', $section->type);
    }

    public function test_creating_a_section_with_a_type_saves(): void
    {
        $page = Page::factory()->create();

        $this->manager($page)
            ->callTableAction('create', data: [
                'type' => 'text-block',
                'enabled' => true,
                'data' => ['heading' => 'Hello'],
            ])
            ->assertHasNoTableActionErrors();

        $section = $page->sections()->first();
        $this->assertSame('text-block', $section?->type);
        $this->assertSame('Hello', $section?->data['heading'] ?? null);
    }

    public function test_edit_modal_hydrates_and_saves_per_type_content_fields(): void
    {
        $page = Page::factory()->create();
        $section = PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'text-block',
            'data' => ['heading' => 'Before', 'alignment' => 'left'],
        ]);

        $this->manager($page)
            ->mountTableAction('edit', $section)
            ->assertSchemaStateSet([
                'type' => 'text-block',
            ]);

        // THE HYDRATED SHAPE OF A RICH FIELD IS NO LONGER ASSERTED, deliberately.
        // It was pinned as the string '<p>Before</p>'; it is now the editor's own
        // document, because the form builds only the SELECTED type's schema and
        // this field's RichEditor is the single component bound to the path.
        //
        // That assertion failed on the change which fixed a live data-corruption
        // bug — one blueprint's editor rewriting another's stored string — while
        // the round trip below, the only part an operator can observe, stayed
        // correct throughout. A test that pins an intermediate representation
        // votes against fixing the thing it sits next to.

        $this->manager($page)
            ->callTableAction('edit', $section, data: [
                'data' => ['heading' => 'After', 'alignment' => 'center'],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('After', $section->refresh()->data['heading']);
        $this->assertSame('center', $section->data['alignment']);
    }

    /**
     * The rich input must not rewrite copy just by being opened. An operator
     * who edits an unrelated field on a section authored before the WYSIWYG
     * change should not find their headings wrapped in paragraph tags.
     */
    public function test_editing_a_section_leaves_untouched_headings_flat(): void
    {
        $page = Page::factory()->create();
        $section = PageSection::factory()->create([
            'page_id' => $page->id,
            'type' => 'text-block',
            'data' => [
                'heading' => 'The Operating System<br />for Longevity',
                'alignment' => 'left',
            ],
        ]);

        $this->manager($page)
            ->callTableAction('edit', $section, data: [
                'data' => [
                    'heading' => '<p>The Operating System<br>for Longevity</p>',
                    'alignment' => 'center',
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            'The Operating System<br />for Longevity',
            $section->refresh()->data['heading'],
        );
    }
}
