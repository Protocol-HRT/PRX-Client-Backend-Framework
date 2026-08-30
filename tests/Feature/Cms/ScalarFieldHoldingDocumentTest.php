<?php

namespace Tests\Feature\Cms;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Cms\FlexibleSectionType;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reproduction: a scalar field holding a rich-editor DOCUMENT.
 *
 * Live data on this install has `comparison-table.heading` — declared
 * `kind: text, max: 255` — holding a ProseMirror doc object, on two pages. The
 * operator reports a 500 when saving that page. This pins what actually
 * happens, so the fix is aimed at the real cause rather than a guessed one.
 */
class ScalarFieldHoldingDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_page_whose_text_field_holds_a_document(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        FlexibleSectionType::create([
            'slug' => 'comparison-table',
            'name' => 'Comparison table',
            'is_active' => true,
            'schema' => ['fields' => [
                ['key' => 'heading', 'kind' => 'text', 'label' => 'Heading', 'max' => 255],
                ['key' => 'others_label', 'kind' => 'text', 'label' => 'Others', 'max' => 120],
                ['key' => 'cta', 'kind' => 'link', 'label' => 'CTA button'],
            ]],
        ]);

        $page = Page::create(['slug' => 'home', 'title' => 'Home', 'is_published' => true]);

        PageSection::create([
            'page_id' => $page->id,
            'type' => 'comparison-table',
            'position' => 1,
            'is_active' => true,
            'data' => [
                // Exactly the shape in production.
                'heading' => [
                    'type' => 'doc',
                    'content' => [[
                        'type' => 'paragraph',
                        'attrs' => ['textAlign' => 'start'],
                        'content' => [['text' => 'Our Peptides Vs. Research-Use-Only', 'type' => 'text']],
                    ]],
                ],
                'others_label' => 'Others',
                'cta' => ['url' => 'find-your-protocol', 'label' => 'Start your program', 'target' => null],
            ],
        ]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component->assertOk();

        $component->call('save');

        $component->assertHasNoFormErrors();
    }
}
