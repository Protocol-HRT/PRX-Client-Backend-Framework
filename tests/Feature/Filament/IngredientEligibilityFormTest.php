<?php

namespace Tests\Feature\Filament;

use App\Enums\Catalog\SexEligibility;
use App\Filament\Resources\Catalog\Ingredients\Pages\CreateIngredient;
use App\Filament\Resources\Catalog\Ingredients\Pages\ListIngredients;
use App\Models\Catalog\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Eligibility tab has to render and save, and the list has to show the
 * classification.
 *
 * Worth its own test because this is the ONE form an operator uses to decide
 * whether a woman is offered testosterone, and a Filament form that throws at
 * render, or silently drops a field, fails in a direction nobody would notice:
 * the ingredient just stays at the permissive default.
 *
 * The list columns are asserted too. They are what tells an operator which
 * substances nobody has classified yet — an unclassified male-only ingredient
 * is indistinguishable from a correctly unisex one without them.
 */
class IngredientEligibilityFormTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create()->refresh();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_an_ingredient_saves_with_eligibility_set(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateIngredient::class)
            ->fillForm([
                'name' => 'Testosterone Cypionate',
                'slug' => 'testosterone-cypionate',
                'sex_eligibility' => SexEligibility::Male->value,
                'min_age' => 25,
                'eligibility_note' => 'Male hormone therapy.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ingredient = Ingredient::firstWhere('slug', 'testosterone-cypionate');

        $this->assertSame(SexEligibility::Male, $ingredient->sex_eligibility);
        $this->assertSame(25, $ingredient->min_age);
        $this->assertNull($ingredient->max_age);
        $this->assertSame('25 and over', $ingredient->ageRangeLabel());
    }

    public function test_a_new_ingredient_defaults_to_anyone_rather_than_a_restriction(): void
    {
        // The failure direction is deliberate: an unset field must OVER-offer a
        // safe substance, never under-offer, because an operator notices a
        // product that should not have appeared and never notices one that
        // silently stopped appearing.
        $this->actAsAdmin();

        Livewire::test(CreateIngredient::class)
            ->fillForm(['name' => 'Glycine', 'slug' => 'glycine'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(SexEligibility::Any, Ingredient::firstWhere('slug', 'glycine')->sex_eligibility);
    }

    public function test_a_max_age_below_the_min_is_rejected(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateIngredient::class)
            ->fillForm([
                'name' => 'Backwards',
                'slug' => 'backwards',
                'min_age' => 60,
                'max_age' => 30,
            ])
            ->call('create')
            ->assertHasFormErrors(['max_age']);
    }

    public function test_the_list_renders_the_eligibility_columns(): void
    {
        $this->actAsAdmin();

        $male = Ingredient::factory()->create([
            'sex_eligibility' => SexEligibility::Male,
            'min_age' => 18,
            'max_age' => 65,
        ]);
        $unisex = Ingredient::factory()->create();

        Livewire::test(ListIngredients::class)
            ->assertCanSeeTableRecords([$male, $unisex])
            ->assertTableColumnStateSet('sex_eligibility', SexEligibility::Male, $male)
            ->assertTableColumnStateSet('sex_eligibility', SexEligibility::Any, $unisex)
            ->assertTableColumnStateSet('age_range', '18 to 65', $male)
            ->assertTableColumnStateSet('age_range', null, $unisex);
    }
}
