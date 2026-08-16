<?php

namespace Tests\Feature\Catalog;

use App\Filament\Resources\Catalog\Products\Pages\EditProduct;
use App\Filament\Resources\Catalog\Products\RelationManagers\IngredientsRelationManager;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\MeasurementUnit;
use App\Models\Catalog\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IngredientsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * Regression: both `ingredients` and the `ingredient_product` pivot carry
     * a `position` column, so unqualified sorts threw an ambiguous-column
     * QueryException as soon as a product had attached ingredients.
     */
    public function test_table_renders_with_attached_ingredients(): void
    {
        $product = Product::factory()->create();
        $unit = MeasurementUnit::factory()->create(['abbreviation' => 'mg']);

        $product->ingredients()->attach(
            Ingredient::factory()->count(2)->create()->pluck('id')->all(),
            ['concentration' => 5, 'concentration_unit_id' => $unit->id],
        );

        Livewire::test(IngredientsRelationManager::class, [
            'ownerRecord' => $product->fresh(),
            'pageClass' => EditProduct::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($product->ingredients);
    }
}
