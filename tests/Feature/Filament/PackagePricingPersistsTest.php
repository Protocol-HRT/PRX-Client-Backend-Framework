<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Catalog\Packages\Pages\EditPackage;
use App\Models\Catalog\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reproduction: the operator reports that a package's pricing fields do not
 * persist — retail, sale, cost and the price suffix.
 */
class PackagePricingPersistsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_fields_persist(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $package = Package::create([
            'name' => 'Performance Stack',
            'slug' => 'performance-stack',
            'retail_price' => 399.00,
            'cost' => 129.00,
            'price_suffix' => '/mo',
        ]);

        Livewire::test(EditPackage::class, ['record' => $package->getRouteKey()])
            ->assertOk()
            ->fillForm([
                'retail_price' => 449.00,
                'sale_price' => 349.00,
                'cost' => 149.00,
                'price_suffix' => '/month',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $package->fresh();

        $this->assertSame('449.00', (string) $fresh->retail_price, 'retail_price did not persist');
        $this->assertSame('349.00', (string) $fresh->sale_price, 'sale_price did not persist');
        $this->assertSame('149.00', (string) $fresh->cost, 'cost did not persist');
        $this->assertSame('/month', $fresh->price_suffix, 'price_suffix did not persist');
    }
}
