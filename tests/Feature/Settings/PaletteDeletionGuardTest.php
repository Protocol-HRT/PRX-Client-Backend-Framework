<?php

namespace Tests\Feature\Settings;

use App\Actions\Settings\UpdateThemeSettingsAction;
use App\Cms\Support\LayoutFields;
use App\Cms\Support\PaletteUsage;
use App\Data\Settings\ThemeSettingsData;
use App\Models\Catalog\CatalogItemSection;
use App\Models\Catalog\Product;
use App\Models\Cms\GlobalSection;
use App\Models\PageSection;
use App\Settings\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Removing a palette colour a section still stores has no frontend fallback:
 * `--sx-bg` resolves to an undefined custom property, the declaration computes
 * to `unset`, and the band renders transparent while its marker class still
 * claims an operator chose a colour. The only place to catch it is before the
 * save, which is what these cover.
 */
class PaletteDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<array{name: string, color: string}> $palette */
    private function save(array $palette): void
    {
        $this->app->make(UpdateThemeSettingsAction::class)->execute(
            ThemeSettingsData::validateAndCreate($this->payload($palette))
        );
    }

    /** @param list<array{name: string, color: string}> $palette */
    private function payload(array $palette): array
    {
        return [
            'primary_color' => '#111111',
            'accent_color' => '#222222',
            'accent_secondary_color' => '#333333',
            'background_color' => '#ffffff',
            'text_color' => '#000000',
            'font_display' => 'Spectral',
            'font_body' => 'Inter',
            'custom_css' => null,
            'frontend_template' => 'atlas',
            'palette' => $palette,
        ];
    }

    private function seedPalette(): void
    {
        $this->save([
            ['name' => 'sand', 'color' => '#e7e0d6'],
            ['name' => 'ink', 'color' => '#111111'],
        ]);
    }

    public function test_removing_an_unused_color_is_allowed(): void
    {
        $this->seedPalette();

        $this->save([['name' => 'ink', 'color' => '#111111']]);

        $this->assertSame(
            ['ink'],
            array_column($this->app->make(ThemeSettings::class)->palette, 'name')
        );
    }

    public function test_removing_a_color_used_at_the_top_level_of_a_section_is_blocked(): void
    {
        $this->seedPalette();

        PageSection::factory()->create([
            'type' => 'hero',
            'data' => ['headline' => 'x', 'style_background_color' => 'sand'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sand/');

        $this->save([['name' => 'ink', 'color' => '#111111']]);
    }

    public function test_removing_a_color_used_only_inside_a_child_block_is_blocked(): void
    {
        $this->seedPalette();

        // The case a SQL LIKE cannot see: the name lives in the parent's JSON,
        // under a child, with nothing at the section's own top level.
        PageSection::factory()->create([
            'type' => 'hero',
            'data' => [
                'headline' => 'x',
                'children' => [
                    ['type' => 'testimonial', 'data' => ['quote' => '<p>y</p>', 'style_text_color' => 'sand']],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sand/');

        $this->save([['name' => 'ink', 'color' => '#111111']]);
    }

    public function test_removing_a_color_used_on_a_global_section_is_blocked(): void
    {
        $this->seedPalette();

        GlobalSection::factory()->create([
            'type' => 'text-block',
            'data' => ['body' => '<p>x</p>', 'style_background_color' => 'sand'],
        ]);

        $this->expectException(RuntimeException::class);

        $this->save([['name' => 'ink', 'color' => '#111111']]);
    }

    public function test_renaming_a_used_color_is_blocked_because_sections_store_the_name(): void
    {
        $this->seedPalette();

        PageSection::factory()->create([
            'type' => 'hero',
            'data' => ['style_background_color' => 'sand'],
        ]);

        $this->expectException(RuntimeException::class);

        $this->save([
            ['name' => 'bone', 'color' => '#e7e0d6'],
            ['name' => 'ink', 'color' => '#111111'],
        ]);
    }

    public function test_recoloring_an_existing_name_is_allowed(): void
    {
        $this->seedPalette();

        PageSection::factory()->create([
            'type' => 'hero',
            'data' => ['style_background_color' => 'sand'],
        ]);

        $this->save([
            ['name' => 'sand', 'color' => '#d0c8bb'],
            ['name' => 'ink', 'color' => '#111111'],
        ]);

        $palette = $this->app->make(ThemeSettings::class)->palette;
        $this->assertSame('#d0c8bb', $palette[0]['color']);
    }

    public function test_a_refused_save_leaves_the_other_theme_fields_untouched(): void
    {
        $this->seedPalette();

        PageSection::factory()->create([
            'type' => 'hero',
            'data' => ['style_background_color' => 'sand'],
        ]);

        try {
            $this->app->make(UpdateThemeSettingsAction::class)->execute(
                ThemeSettingsData::validateAndCreate(array_merge(
                    $this->payload([['name' => 'ink', 'color' => '#111111']]),
                    ['font_display' => 'Georgia'],
                ))
            );
            $this->fail('Expected the guard to refuse the save.');
        } catch (RuntimeException) {
            // expected
        }

        $theme = $this->app->make(ThemeSettings::class);
        $this->assertSame('Spectral', $theme->font_display, 'A refused save must roll back entirely.');
        $this->assertSame(['sand', 'ink'], array_column($theme->palette, 'name'));
    }

    public function test_text_classes_stays_in_lockstep_after_a_permitted_save(): void
    {
        $this->seedPalette();

        $this->save([['name' => 'ink', 'color' => '#111111']]);

        $theme = $this->app->make(ThemeSettings::class);
        $this->assertSame($theme->palette, $theme->text_classes);
    }

    public function test_removing_a_color_used_on_a_catalog_item_section_is_blocked(): void
    {
        $this->seedPalette();

        // The branch no other test reaches: delete the catalog loop from
        // PaletteUsage::rows() and every other test here still passes.
        $product = Product::factory()->create();
        CatalogItemSection::factory()->create([
            'sectionable_type' => $product->getMorphClass(),
            'sectionable_id' => $product->id,
            'type' => 'text-block',
            'data' => ['style_background_color' => 'sand'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sand/');

        $this->save([['name' => 'ink', 'color' => '#111111']]);
    }

    public function test_a_section_with_null_data_is_skipped_without_error(): void
    {
        $this->seedPalette();

        PageSection::factory()->create(['type' => 'text-block', 'data' => null]);

        $this->save([['name' => 'ink', 'color' => '#111111']]);

        $this->assertSame(['ink'], array_column($this->app->make(ThemeSettings::class)->palette, 'name'));
    }

    public function test_a_color_used_by_both_a_section_and_its_child_is_reported_once(): void
    {
        PageSection::factory()->create([
            'type' => 'hero',
            'data' => [
                'style_background_color' => 'sand',
                'children' => [
                    ['type' => 'testimonial', 'data' => ['style_text_color' => 'sand']],
                ],
            ],
        ]);

        $usages = PaletteUsage::find(['sand']);

        $this->assertCount(1, $usages['sand'], 'The same section must not be listed twice.');
    }

    public function test_a_disabled_section_still_counts_as_usage(): void
    {
        $this->seedPalette();

        // Deliberate: a disabled section is one toggle away from being live,
        // and that is the worst moment to discover the color is gone.
        PageSection::factory()->create([
            'type' => 'text-block',
            'enabled' => false,
            'data' => ['style_background_color' => 'sand'],
        ]);

        $this->expectException(RuntimeException::class);

        $this->save([['name' => 'ink', 'color' => '#111111']]);
    }

    public function test_palette_keys_stay_a_subset_of_the_layout_vocabulary(): void
    {
        // Both lists describe the same knobs from different angles. If a
        // name-valued knob is added to one and missed in the other, the guard
        // goes blind to it and the transparent-band bug returns.
        $this->assertSame(
            [],
            array_diff(PaletteUsage::KEYS, LayoutFields::KEYS),
            'Every PaletteUsage key must be a real layout knob.'
        );
    }
}
