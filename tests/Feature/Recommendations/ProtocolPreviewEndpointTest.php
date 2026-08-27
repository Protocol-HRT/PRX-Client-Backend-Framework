<?php

namespace Tests\Feature\Recommendations;

use App\Enums\Catalog\SexEligibility;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\Product;
use App\Models\Kb\HealthGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The endpoint, not the resolver.
 *
 * These exist because the resolver tests passed while the endpoint threw. The
 * resolver returned `collect()` from its empty paths — a Support collection,
 * which has no ->loadMissing() — so the controller 500'd on exactly the
 * outcome this feature was built to produce: a goal that resolves to nothing
 * because the visitor was filtered out. A test that never drives the HTTP
 * layer cannot see that, so these do.
 */
class ProtocolPreviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function goal(string $slug, array $ingredients = []): HealthGoal
    {
        $goal = HealthGoal::create(['name' => ucfirst($slug), 'slug' => $slug]);

        foreach ($ingredients as $ingredient) {
            $goal->ingredients()->attach($ingredient->id, ['relevance_weight' => 50]);
        }

        return $goal;
    }

    public function test_a_fully_restricted_goal_returns_200_and_not_a_500(): void
    {
        // The regression. Every ingredient filtered out => empty collections
        // all the way down => the path that used to throw.
        $male = Ingredient::factory()->create(['sex_eligibility' => SexEligibility::Male]);
        $product = Product::factory()->create();
        $product->ingredients()->attach($male->id);
        $this->goal('hormones', [$male]);

        $response = $this->postJson('/api/v1/protocol/preview', [
            'goals' => ['hormones'],
            'sex' => 'female',
            'age' => 45,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.outcome', 'restricted')
            ->assertJsonPath('data.0.excluded_count', 1)
            ->assertJsonPath('data.0.products', [])
            ->assertJsonPath('meta.filtered', true);
    }

    public function test_a_goal_nobody_has_mapped_reads_as_unmapped_not_restricted(): void
    {
        $this->goal('brand-new');

        $this->postJson('/api/v1/protocol/preview', [
            'goals' => ['brand-new'],
            'sex' => 'female',
            'age' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.outcome', 'unmapped')
            ->assertJsonPath('data.0.excluded_count', 0);
    }

    public function test_it_matches_when_something_is_eligible(): void
    {
        $unisex = Ingredient::factory()->create(['sex_eligibility' => SexEligibility::Any]);
        $product = Product::factory()->create();
        $product->ingredients()->attach($unisex->id);
        $this->goal('weight', [$unisex]);

        $this->postJson('/api/v1/protocol/preview', [
            'goals' => ['weight'],
            'sex' => 'female',
            'age' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.outcome', 'matched')
            ->assertJsonPath('data.0.products.0.slug', $product->slug);
    }

    public function test_omitting_the_answers_filters_nothing_and_says_so(): void
    {
        $male = Ingredient::factory()->create(['sex_eligibility' => SexEligibility::Male]);
        $product = Product::factory()->create();
        $product->ingredients()->attach($male->id);
        $this->goal('hormones', [$male]);

        $this->postJson('/api/v1/protocol/preview', ['goals' => ['hormones']])
            ->assertOk()
            ->assertJsonPath('data.0.outcome', 'matched')
            ->assertJsonPath('meta.filtered', false);
    }

    public function test_a_product_level_restriction_reads_as_restricted_not_unmapped(): void
    {
        // The goal maps a UNISEX ingredient, so nothing is excluded at the
        // ingredient hop and excluded_count is 0. The only product holding it
        // also holds a male-only ingredient, so a female visitor gets nothing.
        // Reading the count alone called this "unmapped" and told her we had
        // not built the goal out — when we had, and it is not for her.
        $unisex = Ingredient::factory()->create(['sex_eligibility' => SexEligibility::Any]);
        $male = Ingredient::factory()->create(['sex_eligibility' => SexEligibility::Male]);

        $combo = Product::factory()->create();
        $combo->ingredients()->attach([$unisex->id, $male->id]);

        $this->goal('recovery', [$unisex]);

        $this->postJson('/api/v1/protocol/preview', [
            'goals' => ['recovery'],
            'sex' => 'female',
            'age' => 40,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.outcome', 'restricted')
            ->assertJsonPath('data.0.excluded_count', 0)
            ->assertJsonPath('data.0.products', []);
    }

    public function test_a_goal_whose_ingredients_have_no_products_reads_as_unmapped(): void
    {
        // Mapped, eligible, but nothing stocked holds it. That is a catalogue
        // gap, not a restriction, and must not tell the visitor they were
        // filtered out.
        $unisex = Ingredient::factory()->create(['sex_eligibility' => SexEligibility::Any]);
        $this->goal('longevity', [$unisex]);

        $this->postJson('/api/v1/protocol/preview', [
            'goals' => ['longevity'],
            'sex' => 'female',
            'age' => 40,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.outcome', 'unmapped');
    }

    public function test_it_rejects_an_unknown_goal_and_an_out_of_range_age(): void
    {
        $this->postJson('/api/v1/protocol/preview', ['goals' => ['no-such-goal']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('goals.0');

        $this->goal('weight');

        $this->postJson('/api/v1/protocol/preview', ['goals' => ['weight'], 'age' => 12])
            ->assertStatus(422)
            ->assertJsonValidationErrors('age');
    }

    public function test_it_does_not_leak_the_weights_or_which_ingredient_was_excluded(): void
    {
        // excluded_count is a count, not a list. Returning the names would let
        // anyone enumerate which substances are sex-gated by varying the body.
        $male = Ingredient::factory()->create(['sex_eligibility' => SexEligibility::Male]);
        $this->goal('hormones', [$male]);

        $response = $this->postJson('/api/v1/protocol/preview', [
            'goals' => ['hormones'],
            'sex' => 'female',
        ]);

        $response->assertOk();
        $body = $response->json();

        $this->assertStringNotContainsString($male->name, json_encode($body));
        $this->assertStringNotContainsString('relevance_weight', json_encode($body));
    }
}
