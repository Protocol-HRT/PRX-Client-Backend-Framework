<?php

namespace Tests\Feature\Recommendations;

use App\Enums\Catalog\SexEligibility;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Kb\HealthGoal;
use App\Services\Recommendations\GoalRecommendationResolver;
use App\Services\Recommendations\VisitorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the things this gate must never get wrong.
 *
 * The stakes here are different from a ranking test: a wrong order is a bad
 * recommendation, a wrong gate is offering testosterone to a woman. So the
 * assertions are about what must NOT appear, and about the two directions the
 * gate can fail — over-offering (a restriction that did not apply) and
 * under-offering (a restriction invented from an absent answer).
 */
class GoalRecommendationResolverTest extends TestCase
{
    use RefreshDatabase;

    private GoalRecommendationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new GoalRecommendationResolver();
    }

    private function goalWith(array $ingredients): HealthGoal
    {
        $goal = HealthGoal::create(['name' => 'Test goal', 'slug' => 'test-goal-'.uniqid()]);

        foreach ($ingredients as $weight => $ingredient) {
            $goal->ingredients()->attach($ingredient->id, ['relevance_weight' => $weight]);
        }

        return $goal;
    }

    private function ingredient(SexEligibility $sex = SexEligibility::Any, ?int $min = null, ?int $max = null): Ingredient
    {
        return Ingredient::factory()->create([
            'sex_eligibility' => $sex,
            'min_age' => $min,
            'max_age' => $max,
        ]);
    }

    private function productWith(Ingredient ...$ingredients): Product
    {
        $product = Product::factory()->create();
        $product->ingredients()->attach(collect($ingredients)->pluck('id'));

        return $product;
    }

    public function test_a_male_only_ingredient_is_never_offered_to_a_female_visitor(): void
    {
        $testosterone = $this->ingredient(SexEligibility::Male);
        $goal = $this->goalWith([60 => $testosterone]);
        $this->productWith($testosterone);

        $result = $this->resolver->resolve($goal, new VisitorProfile('female', 45));

        $this->assertCount(0, $result['ingredients']);
        $this->assertCount(0, $result['products']);
        $this->assertSame(1, $result['excluded_count']);
    }

    public function test_a_high_weight_cannot_float_an_ineligible_ingredient_past_the_gate(): void
    {
        // The gate runs before ranking. A weight of 100 must not beat it.
        $male = $this->ingredient(SexEligibility::Male);
        $unisex = $this->ingredient();
        $goal = $this->goalWith([100 => $male, 1 => $unisex]);

        $offered = $this->resolver->ingredientsFor($goal, new VisitorProfile('female', 30));

        $this->assertSame([$unisex->id], $offered->pluck('id')->all());
    }

    public function test_an_unasked_question_does_not_filter_anything(): void
    {
        // Null is "not asked", not "answered nothing". A visitor who never
        // took the quiz must see the whole shelf.
        $male = $this->ingredient(SexEligibility::Male);
        $female = $this->ingredient(SexEligibility::Female);
        $goal = $this->goalWith([50 => $male, 40 => $female]);

        $offered = $this->resolver->ingredientsFor($goal, new VisitorProfile());

        $this->assertCount(2, $offered);
    }

    public function test_a_self_described_answer_does_not_silently_narrow_options(): void
    {
        // leads.gender is free-form by contract. An answer that lands in no
        // bucket must not be guessed into one.
        $male = $this->ingredient(SexEligibility::Male);
        $female = $this->ingredient(SexEligibility::Female);
        $goal = $this->goalWith([50 => $male, 40 => $female]);

        $offered = $this->resolver->ingredientsFor($goal, new VisitorProfile('non-binary', 30));

        $this->assertCount(2, $offered);
    }

    public function test_age_bounds_gate_in_both_directions_and_null_is_unbounded(): void
    {
        $over25 = $this->ingredient(SexEligibility::Any, min: 25);
        $under40 = $this->ingredient(SexEligibility::Any, max: 40);
        $unbounded = $this->ingredient();
        $goal = $this->goalWith([50 => $over25, 40 => $under40, 30 => $unbounded]);

        $young = $this->resolver->ingredientsFor($goal, new VisitorProfile(null, 20))->pluck('id');
        $this->assertEqualsCanonicalizing([$under40->id, $unbounded->id], $young->all());

        $older = $this->resolver->ingredientsFor($goal, new VisitorProfile(null, 60))->pluck('id');
        $this->assertEqualsCanonicalizing([$over25->id, $unbounded->id], $older->all());

        $middle = $this->resolver->ingredientsFor($goal, new VisitorProfile(null, 30))->pluck('id');
        $this->assertCount(3, $middle);
    }

    public function test_a_combination_product_is_male_only_if_any_ingredient_is(): void
    {
        // The conservative reading, and the one a generated protocol uses.
        $unisex = $this->ingredient();
        $male = $this->ingredient(SexEligibility::Male);
        $goal = $this->goalWith([50 => $unisex]);

        $combo = $this->productWith($unisex, $male);
        $clean = $this->productWith($unisex);

        $offered = $this->resolver->strictProductsFor($goal, new VisitorProfile('female', 30));

        $this->assertSame([$clean->id], $offered->pluck('id')->all());
        $this->assertNotContains($combo->id, $offered->pluck('id')->all());
    }

    public function test_a_product_with_no_ingredients_is_ineligible_rather_than_unrestricted(): void
    {
        // The bypass this rule closes: an unclassified product must not become
        // the hole the whole gate leaks through.
        $unisex = $this->ingredient();
        $goal = $this->goalWith([50 => $unisex]);
        $orphan = Product::factory()->create();

        $offered = $this->resolver->strictProductsFor($goal, new VisitorProfile('female', 30));

        $this->assertNotContains($orphan->id, $offered->pluck('id')->all());
        $this->assertFalse($this->resolver->productIsSafe($orphan, new VisitorProfile()));
    }

    public function test_a_stack_is_withheld_when_any_product_in_it_is_unsafe(): void
    {
        // A stack is bought whole — the visitor cannot decline one item.
        $unisex = $this->ingredient();
        $male = $this->ingredient(SexEligibility::Male);
        $goal = $this->goalWith([50 => $unisex]);

        $relevant = $this->productWith($unisex);
        $unsafe = $this->productWith($male);

        $safeStack = Package::factory()->create();
        $safeStack->products()->attach($relevant->id);

        $mixedStack = Package::factory()->create();
        $mixedStack->products()->attach([$relevant->id, $unsafe->id]);

        $offered = $this->resolver->packagesFor($goal, new VisitorProfile('female', 30));

        $this->assertSame([$safeStack->id], $offered->pluck('id')->all());
    }

    public function test_a_stack_is_not_rejected_merely_for_holding_an_unrelated_product(): void
    {
        // The regression this test exists for: judging a stack by goal
        // RELEVANCE rather than SAFETY rejected every package in the
        // catalogue. Safety must hold for all; relevance for one.
        $goalIngredient = $this->ingredient();
        $otherIngredient = $this->ingredient();
        $goal = $this->goalWith([50 => $goalIngredient]);

        $relevant = $this->productWith($goalIngredient);
        $unrelatedButSafe = $this->productWith($otherIngredient);

        $stack = Package::factory()->create();
        $stack->products()->attach([$relevant->id, $unrelatedButSafe->id]);

        $offered = $this->resolver->packagesFor($goal, new VisitorProfile('female', 30));

        $this->assertSame([$stack->id], $offered->pluck('id')->all());
    }

    public function test_excluded_count_separates_a_filtered_empty_from_an_unmapped_one(): void
    {
        // Different copy: "nothing suitable for you" vs "not built yet".
        $filtered = $this->goalWith([50 => $this->ingredient(SexEligibility::Male)]);
        $unmapped = $this->goalWith([]);

        $this->assertSame(1, $this->resolver->resolve($filtered, new VisitorProfile('female', 30))['excluded_count']);
        $this->assertSame(1, $this->resolver->resolve($filtered, new VisitorProfile('female', 30))['mapped_count']);

        $this->assertSame(0, $this->resolver->resolve($unmapped, new VisitorProfile('female', 30))['excluded_count']);
        $this->assertSame(0, $this->resolver->resolve($unmapped, new VisitorProfile('female', 30))['mapped_count']);
    }

    public function test_first_line_outranks_a_higher_relevance_weight(): void
    {
        $weighted = $this->ingredient();
        $firstLine = $this->ingredient();
        $goal = HealthGoal::create(['name' => 'Ranked', 'slug' => 'ranked-'.uniqid()]);
        $goal->ingredients()->attach($weighted->id, ['relevance_weight' => 90]);
        $goal->ingredients()->attach($firstLine->id, ['relevance_weight' => 10, 'is_first_line' => true]);

        $offered = $this->resolver->ingredientsFor($goal, new VisitorProfile());

        $this->assertSame($firstLine->id, $offered->first()->id);
    }
}
