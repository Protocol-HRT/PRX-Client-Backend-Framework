<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Catalog\Packages\Pages\CreatePackage;
use App\Models\Catalog\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The package "Kind" field has to actually save.
 *
 * Catalog writes route through a Spatie Data DTO, which silently drops any
 * property it does not declare — the exact trap that made the ingredient
 * eligibility fields save as their defaults while the form showed the right
 * values. Nothing about that failure is visible in the UI, so it needs a test
 * rather than a look.
 */
class PackageTierFormTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_a_package_saves_its_tier(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreatePackage::class)
            ->fillForm(['name' => 'Lean Protocol', 'slug' => 'lean-protocol', 'tier' => 'protocol'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('protocol', Package::firstWhere('slug', 'lean-protocol')->tier);
    }

    public function test_tier_is_optional_and_defaults_to_unclassified(): void
    {
        // Null is the correct starting state — an existing catalogue has no
        // tiers, and defaulting one would silently put every package into a
        // quiz price range it was never meant to be in.
        $this->actAsAdmin();

        Livewire::test(CreatePackage::class)
            ->fillForm(['name' => 'Unclassified', 'slug' => 'unclassified'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Package::firstWhere('slug', 'unclassified')->tier);
    }
}
