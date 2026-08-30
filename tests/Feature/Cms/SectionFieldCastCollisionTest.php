<?php

namespace Tests\Feature\Cms;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\RelationManagers\SectionsRelationManager;
use App\Models\Cms\FlexibleSectionType;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use App\Services\Cms\SectionRegistry;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

/**
 * One blueprint's editor must never rewrite another blueprint's value.
 *
 * ─── The bug, which reached a visitor ──────────────────────────────────
 *
 * `SectionFormBuilder` built one Group per registered section type, every one
 * gated by `visible()` and every one bound into the same `data.*` path.
 * `visible()` hides a component; it does not stop its state cast. So a
 * `RichEditor` declared for `heading` on 26 blueprints applied
 * `RichEditorStateCast` to comparison-table's `heading` too — wrapping a stored
 * string into a ProseMirror document, handing that object to a TextInput, and
 * writing the document back on save.
 *
 * The operator saw `[object Object]` in the admin. So did visitors, inside an
 * `<h2>` on the live home page.
 *
 * The first test is the reproduction. The second is the general guard, because
 * `heading` was one of four colliding keys and the next collision will be
 * introduced by a blueprint nobody has written yet.
 */
class SectionFieldCastCollisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_text_heading_survives_the_edit_modal(): void
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
            ]],
        ]);

        $page = Page::create(['slug' => 'home', 'title' => 'Home', 'is_published' => true]);

        $section = PageSection::create([
            'page_id' => $page->id,
            'type' => 'comparison-table',
            'position' => 1,
            'is_active' => true,
            'data' => ['heading' => 'Our Peptides Vs. Research-Use-Only', 'others_label' => 'Others'],
        ]);

        $mounted = Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditPage::class,
        ])
            ->mountTableAction('edit', $section->getKey())
            ->get('mountedActions');

        $heading = $mounted[0]['data']['data']['heading'] ?? null;

        $this->assertIsString(
            $heading,
            'the edit modal turned a stored string into a '.gettype($heading)
            .' — another blueprint\'s state cast reached this field',
        );
        $this->assertSame('Our Peptides Vs. Research-Use-Only', $heading);

        // AND THE FIELD MUST ACTUALLY EXIST. Without this, the assertions above
        // would also pass if the schema closure returned nothing at all: no
        // component means no cast, so the raw string survives the fill untouched
        // while the operator stares at an empty modal. A save round trip is the
        // cheapest proof that the field was really built and really bound —
        // and this is the only test in the suite that round-trips a FLEXIBLE
        // type through the form.
        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditPage::class,
        ])
            ->callTableAction('edit', $section->getKey(), data: [
                'data' => ['heading' => 'Edited by hand', 'others_label' => 'Them'],
            ])
            ->assertHasNoTableActionErrors();

        $section->refresh();

        $this->assertSame('Edited by hand', $section->data['heading']);
        $this->assertSame('Them', $section->data['others_label']);
    }

    public function test_no_field_key_is_bound_by_a_rich_editor_in_one_blueprint_and_a_plain_input_in_another(): void
    {
        $byKey = [];

        foreach (app(SectionRegistry::class)->all() as $type => $definition) {
            try {
                $schema = $definition->formSchema();
            } catch (Throwable) {
                // A blueprint that cannot build outside a request context is not
                // what this test is about.
                continue;
            }

            $this->collectFields($schema, function (Field $field) use (&$byKey, $type): void {
                $byKey[$field->getName()][$field instanceof RichEditor ? 'rich' : 'plain'][] = $type;
            });
        }

        // A guard that walked nothing would pass forever. The blueprints build
        // ~187 fields between them, so anything near zero means `formSchema()`
        // is throwing and being swallowed, not that the codebase is clean.
        $this->assertGreaterThan(
            100,
            count($byKey),
            'only '.count($byKey).' field keys were collected — the walk is not reaching the blueprints',
        );
        $this->assertArrayHasKey('heading', $byKey, 'the walk missed `heading`, which every blueprint has');

        $collisions = [];

        foreach ($byKey as $key => $kinds) {
            if (isset($kinds['rich'], $kinds['plain'])) {
                $collisions[] = sprintf(
                    '%s (rich in %s; plain in %s)',
                    $key,
                    implode('/', array_slice($kinds['rich'], 0, 3)),
                    implode('/', array_slice($kinds['plain'], 0, 3)),
                );
            }
        }

        // The form now builds only the SELECTED type's schema, so a collision is
        // no longer able to corrupt anything. It is still worth failing on:
        // sharing a key between a rich and a plain field means the same payload
        // means two things, and the next component to grow a state cast turns
        // that back into data loss.
        $this->assertSame([], $collisions, "These field keys are a rich editor in one blueprint and a plain input in another:\n".implode("\n", $collisions));
    }

    private function collectFields(iterable $components, callable $visit): void
    {
        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            if ($component instanceof Field) {
                $visit($component);
            }

            foreach (['getChildComponents', 'getDefaultChildComponents', 'getComponents'] as $method) {
                if (! method_exists($component, $method)) {
                    continue;
                }

                try {
                    $children = $component->{$method}();
                } catch (Throwable) {
                    // Outside a live schema `getChildComponents()` throws on an
                    // uninitialised container, so fall through to the next
                    // accessor rather than giving up on this component — giving
                    // up is what made an earlier version of this walk see 25
                    // fields instead of 187 and pass while proving nothing.
                    continue;
                }

                if (is_iterable($children)) {
                    $this->collectFields($children, $visit);
                }

                break;
            }
        }
    }
}
