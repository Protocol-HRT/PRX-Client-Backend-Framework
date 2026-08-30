<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Catalog\Packages\Pages\EditPackage;
use App\Filament\Resources\Catalog\Products\Pages\EditProduct;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Layout panel writes something.
 *
 * It did not. `ProductData` and `PackageData` carried no `detail_layout`
 * property and neither update action referenced the column, so the five
 * Layout selects — template, accordion placement, Pair With counts, rails —
 * were filled by the operator, validated into a DTO that dropped them, and
 * saved successfully having written nothing. Every value in that column came
 * from the fill scripts.
 *
 * It was invisible for two reasons worth remembering. A save that persists
 * nothing looks identical to a frontend that ignores the setting — and here
 * BOTH were true at once, so fixing either alone would still have shown the
 * operator no change. And the storefront defaults a missing knob rather than
 * erroring, so a page whose layout never saved renders perfectly.
 *
 * Mounts the real Filament forms rather than calling the actions, because the
 * defect lived in the seam between the form and the DTO — a test that called
 * the action directly would have passed throughout.
 */
class DetailLayoutPersistsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_a_products_layout_knobs_persist(): void
    {
        $product = Product::factory()->create(['detail_layout' => null]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertOk()
            ->fillForm([
                'detail_layout.template' => 'conversion',
                'detail_layout.accordions.placement' => 'side',
                'detail_layout.pair_with.desktop' => 2,
                'detail_layout.pair_with.mobile' => 1,
                'detail_layout.rails' => ['related', 'stacks'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $layout = $product->fresh()->detail_layout;

        $this->assertSame('conversion', $layout['template'] ?? null);
        // The nested key is the one the operator reported and the one a dotted
        // Filament path is most likely to lose.
        $this->assertSame('side', $layout['accordions']['placement'] ?? null);
        $this->assertSame(['related', 'stacks'], $layout['rails'] ?? null);
    }

    public function test_a_packages_layout_knobs_persist(): void
    {
        $package = Package::factory()->create(['detail_layout' => null]);

        Livewire::test(EditPackage::class, ['record' => $package->getRouteKey()])
            ->assertOk()
            ->fillForm([
                'detail_layout.template' => 'conversion',
                'detail_layout.accordions.placement' => 'below',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $layout = $package->fresh()->detail_layout;

        $this->assertSame('conversion', $layout['template'] ?? null);
        $this->assertSame('below', $layout['accordions']['placement'] ?? null);
    }

    /**
     * THE REVIEW GATE CAUGHT THIS, and it nearly shipped.
     *
     * Filament hydrates an untouched CheckboxList as [] and dehydrates it the
     * same way, so once the DTO carried detail_layout, saving a product
     * WITHOUT OPENING THE LAYOUT TAB wrote `rails: []` — which the storefront
     * read as "the operator chose no rails". Fixing a typo in a subtitle would
     * have silently deleted the recommendation rails from the page. Sixteen of
     * seventeen catalog items were one save away from it.
     *
     * That is the exact class of invisible save behaviour this whole change
     * exists to remove, reintroduced one layer up by the fix for it.
     */
    public function test_saving_without_touching_the_layout_tab_writes_nothing(): void
    {
        $product = Product::factory()->create(['detail_layout' => null]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertOk()
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $product->fresh()->detail_layout ?? []);
    }

    /**
     * An existing choice must survive an edit that ignores the Layout tab —
     * pruning empties must not prune what was actually set.
     */
    public function test_an_untouched_save_preserves_existing_layout_choices(): void
    {
        $product = Product::factory()->create([
            'detail_layout' => [
                'template' => 'conversion',
                'accordions' => ['placement' => 'side'],
                'rails' => ['related', 'stacks'],
            ],
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertOk()
            ->call('save')
            ->assertHasNoFormErrors();

        $layout = $product->fresh()->detail_layout;

        $this->assertSame('conversion', $layout['template'] ?? null);
        $this->assertSame('side', $layout['accordions']['placement'] ?? null);
        $this->assertSame(['related', 'stacks'], $layout['rails'] ?? null);
    }

    /**
     * "No rails" is the explicit `none` token, not an empty list — an empty
     * list is indistinguishable from a control nobody touched, so it is
     * pruned. `none` is deliberately not redundant with leaving the knob
     * unset, the same way the section spacing scale uses it.
     */
    public function test_choosing_none_persists_as_an_explicit_token(): void
    {
        $product = Product::factory()->create([
            'detail_layout' => ['rails' => ['related', 'stacks'], 'template' => 'conversion'],
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertOk()
            ->fillForm(['detail_layout.rails' => ['none']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['none'], $product->fresh()->detail_layout['rails'] ?? null);
    }
}
