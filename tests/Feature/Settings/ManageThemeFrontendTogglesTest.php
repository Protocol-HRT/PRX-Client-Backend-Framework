<?php

namespace Tests\Feature\Settings;

use App\Filament\Pages\Settings\ManageTheme;
use App\Models\User;
use App\Settings\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Frontend section's booleans, driven through the REAL form save.
 *
 * This project has three times shipped a Filament form that reported success
 * and wrote nothing, because the field was missing from the Data object or
 * from the update action's assignment list. Exercising the settings object
 * directly cannot catch that: it skips getState(), validateAndCreate() and
 * the action entirely, which is exactly where those bugs live.
 *
 * Both directions are asserted on purpose. Turning a flag ON is the easy
 * half; a value silently dropped on the way through reads as `false`, so
 * only the OFF direction distinguishes "saved false" from "never saved".
 */
class ManageThemeFrontendTogglesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super_admin', 'web');

        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        // A valid palette so the save under test is decided by the toggle,
        // not by the palette guard the sibling test already covers.
        $theme = $this->app->make(ThemeSettings::class);
        $theme->palette = [['name' => 'ink', 'color' => '#111111']];
        $theme->save();
    }

    private function storedZoom(): bool
    {
        return $this->app->make(ThemeSettings::class)->refresh()->product_zoom_enabled;
    }

    public function test_product_zoom_defaults_to_off(): void
    {
        $this->assertFalse($this->storedZoom());
    }

    public function test_turning_product_zoom_on_persists_through_the_form(): void
    {
        Livewire::test(ManageTheme::class)
            ->fillForm(['product_zoom_enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->storedZoom());
    }

    public function test_turning_product_zoom_back_off_persists_through_the_form(): void
    {
        $theme = $this->app->make(ThemeSettings::class);
        $theme->product_zoom_enabled = true;
        $theme->save();

        Livewire::test(ManageTheme::class)
            ->fillForm(['product_zoom_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->storedZoom());
    }

    /**
     * The form hydrates from the stored settings, so an operator opening the
     * page sees the real state rather than a control showing a default it
     * never wrote — the failure mode that once hit a consent checkbox.
     */
    public function test_the_form_loads_the_stored_value(): void
    {
        $theme = $this->app->make(ThemeSettings::class);
        $theme->product_zoom_enabled = true;
        $theme->save();

        Livewire::test(ManageTheme::class)
            ->assertFormSet(['product_zoom_enabled' => true]);
    }
}
