<?php

namespace Tests\Feature\Settings;

use App\Filament\Pages\Settings\ManageTheme;
use App\Models\PageSection;
use App\Models\User;
use App\Settings\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The form half of the palette deletion guard.
 *
 * UpdateThemeSettingsAction is the real block — see PaletteDeletionGuardTest.
 * What only the form can get wrong is covered here: that the closure rule is in
 * a shape Filament actually evaluates, and that a refusal lands as an error on
 * the palette field rather than escaping as an unhandled exception.
 *
 * The page's own render smoke test already lives in
 * tests/Feature/Filament/SettingsPagesRenderTest.php.
 */
class ManageThemePaletteFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super_admin', 'web');

        // refresh() loads DB-default columns (is_active) the factory instance
        // lacks — same reason as SettingsPagesRenderTest.
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $theme = $this->app->make(ThemeSettings::class);
        $theme->palette = [
            ['name' => 'sand', 'color' => '#e7e0d6'],
            ['name' => 'ink', 'color' => '#111111'],
        ];
        $theme->save();
    }

    /** @return list<string> */
    private function storedPaletteNames(): array
    {
        return array_column($this->app->make(ThemeSettings::class)->refresh()->palette, 'name');
    }

    public function test_removing_an_unused_color_passes_form_validation(): void
    {
        Livewire::test(ManageTheme::class)
            ->fillForm(['palette' => [['name' => 'ink', 'color' => '#111111']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['ink'], $this->storedPaletteNames());
    }

    public function test_removing_a_used_color_fails_on_the_palette_field(): void
    {
        PageSection::factory()->create([
            'type' => 'hero',
            'data' => ['style_background_color' => 'sand'],
        ]);

        Livewire::test(ManageTheme::class)
            ->fillForm(['palette' => [['name' => 'ink', 'color' => '#111111']]])
            ->call('save')
            ->assertHasFormErrors(['palette']);

        $this->assertSame(['sand', 'ink'], $this->storedPaletteNames());
    }

    public function test_a_color_used_only_by_a_child_block_fails_on_the_palette_field(): void
    {
        PageSection::factory()->create([
            'type' => 'hero',
            'data' => [
                'children' => [
                    ['type' => 'testimonial', 'data' => ['style_background_color' => 'sand']],
                ],
            ],
        ]);

        Livewire::test(ManageTheme::class)
            ->fillForm(['palette' => [['name' => 'ink', 'color' => '#111111']]])
            ->call('save')
            ->assertHasFormErrors(['palette']);

        $this->assertSame(['sand', 'ink'], $this->storedPaletteNames());
    }
}
