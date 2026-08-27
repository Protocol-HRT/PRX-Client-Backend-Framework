<?php

namespace Tests\Feature\Api\V1\Kb;

use App\Models\Catalog\Ingredient;
use App\Models\Kb\Compound;
use App\Models\Kb\HealthGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quiz's vocabulary, and the boundary around it.
 *
 * Two rules are pinned here and both are easy to undo by accident:
 *
 * - **`is_active` and `show_in_quiz` are different questions.** Withdrawing a
 *   goal from intake must not withdraw it from the surfaces that merely NAME
 *   it, or a knowledge-base page loses the goal it was explaining.
 * - **The mappings never leave the building.** They are how a recommendation
 *   is derived, and deriving is server-side — the weighted edges are the thing
 *   a clinician built and a competitor would copy.
 */
class HealthGoalEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health-goals')->assertOk();
    }

    public function test_it_returns_goals_offered_in_the_quiz(): void
    {
        HealthGoal::factory()->create(['name' => 'Sleep', 'slug' => 'sleep']);

        $this->getJson('/api/v1/health-goals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'sleep')
            ->assertJsonPath('meta.count', 1);
    }

    public function test_a_goal_withdrawn_from_the_quiz_is_hidden_by_default(): void
    {
        HealthGoal::factory()->hiddenFromQuiz()->create(['slug' => 'retired']);

        $this->getJson('/api/v1/health-goals')->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * The other half of that rule. A withdrawn goal is still live for
     * everything mapped to it, so a consumer that NAMES goals rather than
     * offering them must still be able to resolve it.
     */
    public function test_a_goal_withdrawn_from_the_quiz_is_still_reachable_with_all(): void
    {
        HealthGoal::factory()->hiddenFromQuiz()->create(['slug' => 'retired']);

        $this->getJson('/api/v1/health-goals?all=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'retired');
    }

    public function test_an_inactive_goal_is_hidden_even_from_all(): void
    {
        HealthGoal::factory()->inactive()->create(['slug' => 'gone']);

        $this->getJson('/api/v1/health-goals?all=1')->assertOk()->assertJsonCount(0, 'data');
    }

    /** A blank card is worse than a terse one, so the fallback lives server-side. */
    public function test_prompt_falls_back_to_the_name(): void
    {
        HealthGoal::factory()->create(['name' => 'Sleep', 'slug' => 'sleep', 'prompt' => null]);

        $this->getJson('/api/v1/health-goals')
            ->assertOk()
            ->assertJsonPath('data.0.prompt', 'Sleep');
    }

    /**
     * The mappings are how a recommendation is derived, and deriving is
     * server-side. Shipping the weighted edges would hand over the mapping a
     * clinician built and tell a visitor what they are about to be sold.
     */
    public function test_the_mappings_are_never_exposed(): void
    {
        $goal = HealthGoal::factory()->create(['slug' => 'recovery']);
        $ingredient = Ingredient::create(['name' => 'BPC-157', 'slug' => 'bpc-157']);
        $compound = Compound::factory()->create(['slug' => 'bpc-157']);

        $goal->ingredients()->attach($ingredient->id, ['relevance_weight' => 95, 'is_first_line' => true]);
        $goal->compounds()->attach($compound->id);

        $body = $this->getJson('/api/v1/health-goals')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('ingredients', $body);
        $this->assertArrayNotHasKey('products', $body);
        $this->assertArrayNotHasKey('compounds', $body);
        $this->assertStringNotContainsString('relevance_weight', json_encode($body));
    }

    public function test_tree_mode_nests_children_and_omits_them_from_the_top_level(): void
    {
        $parent = HealthGoal::factory()->create(['name' => 'Longevity', 'slug' => 'longevity']);
        HealthGoal::factory()->create(['name' => 'Cognition', 'slug' => 'cognition', 'parent_id' => $parent->id]);

        $this->getJson('/api/v1/health-goals?tree=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'longevity')
            ->assertJsonPath('data.0.children.0.slug', 'cognition');
    }

    /** Flat is the default, and it must not silently drop children. */
    public function test_the_flat_list_includes_children(): void
    {
        $parent = HealthGoal::factory()->create(['slug' => 'longevity']);
        HealthGoal::factory()->create(['slug' => 'cognition', 'parent_id' => $parent->id]);

        $this->getJson('/api/v1/health-goals')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_goals_come_back_in_position_order(): void
    {
        HealthGoal::factory()->create(['slug' => 'second', 'position' => 20]);
        HealthGoal::factory()->create(['slug' => 'first', 'position' => 10]);

        $this->getJson('/api/v1/health-goals')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'first')
            ->assertJsonPath('data.1.slug', 'second');
    }
}
