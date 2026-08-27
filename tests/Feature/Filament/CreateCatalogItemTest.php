<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Catalog\Packages\Pages\CreatePackage;
use App\Filament\Resources\Catalog\Products\Pages\CreateProduct;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Creating a catalog item with only its required fields must work.
 *
 * This is a regression test for a bug that shipped and was reported as "new
 * products don't save". Two causes stacked, and neither said anything:
 *
 * 1. Filament repeaters default to ONE item. Almost every repeater in this
 *    admin marks an inner field required, so a new product arrived carrying a
 *    blank `highlights` row that failed validation — on the Merchandising tab,
 *    which an operator filling in Details has never opened. Filament reported
 *    the error against a field on a hidden tab, so the Create button appeared
 *    to do nothing at all.
 * 2. With the repeater emptied, the DTO then rejected it: an `array` property
 *    gets `required`, and Laravel's `required` FAILS on `[]`. The always-there
 *    blank row had been masking that for as long as the form existed.
 *
 * The fix for (1) is admin-wide in AppServiceProvider; for (2) it is `#[Present]`
 * on the optional array properties. This test covers both, because fixing
 * either alone still leaves the item unsaveable.
 */
class CreateCatalogItemTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_a_product_saves_with_only_its_required_fields(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Minimal Product', 'slug' => 'minimal-product'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['slug' => 'minimal-product']);
    }

    public function test_a_package_saves_with_only_its_required_fields(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreatePackage::class)
            ->fillForm(['name' => 'Minimal Package', 'slug' => 'minimal-package'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('packages', ['slug' => 'minimal-package']);
    }

    /**
     * The half that is easy to undo by "tidying" the DTO. An operator who
     * deletes every highlight must be able to save the result.
     */
    public function test_optional_repeaters_may_be_submitted_empty(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'No Extras',
                'slug' => 'no-extras',
                'gallery' => [],
                'highlights' => [],
                'detail_sections' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'no-extras')->firstOrFail();
        $this->assertSame([], $product->highlights ?? []);
    }

    /** Ingredients attach on EDIT — the relation needs a record to hang off. */
    public function test_a_new_product_lands_on_the_edit_page_where_ingredients_can_be_attached(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Redirects', 'slug' => 'redirects'])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'redirects')->firstOrFail();

        $this->get(\App\Filament\Resources\Catalog\Products\ProductResource::getUrl('edit', ['record' => $product]))
            ->assertOk()
            ->assertSee('Ingredients');
    }
}
