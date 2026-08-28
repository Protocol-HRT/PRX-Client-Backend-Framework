<?php

namespace Tests\Feature\Quiz;

use App\Enums\Quiz\QuizQuestionKind;
use App\Models\Kb\HealthGoal;
use App\Models\Lead;
use App\Models\Quiz\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Submitting the quiz as a lead.
 *
 * The rule under test throughout: `visible_when` is a CONSTRAINT, not a
 * rendering hint. The browser hides a question whose conditions fail, but a
 * submission is just an HTTP request — so the server re-evaluates the same
 * conditions against what actually arrived.
 */
class QuizLeadSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        HealthGoal::create(['name' => 'Weight', 'slug' => 'weight-management', 'show_in_quiz' => true]);
        HealthGoal::create(['name' => 'Sleep', 'slug' => 'sleep', 'show_in_quiz' => true]);

        $this->quiz = Quiz::create(['name' => 'Intake', 'slug' => 'intake', 'is_active' => true, 'is_default' => true]);

        $goals = $this->quiz->steps()->create(['slug' => 'goals', 'name' => 'Goals', 'position' => 1, 'is_active' => true]);
        $goals->questions()->create([
            'slug' => 'health_goals', 'kind' => QuizQuestionKind::HealthGoals,
            'prompt' => 'Goals?', 'position' => 1, 'is_active' => true, 'is_required' => true,
        ]);

        $today = $this->quiz->steps()->create(['slug' => 'today', 'name' => 'Today', 'position' => 2, 'is_active' => true]);
        $today->questions()->create([
            'slug' => 'weight', 'kind' => QuizQuestionKind::Measurement, 'prompt' => 'Weight',
            'position' => 1, 'is_active' => true, 'is_required' => true,
            'config' => ['measure' => 'weight', 'min_kg' => 40, 'max_kg' => 205],
        ]);
        $today->questions()->create([
            'slug' => 'goal_weight', 'kind' => QuizQuestionKind::Measurement, 'prompt' => 'Target',
            'position' => 2, 'is_active' => true, 'is_required' => false,
            'config' => ['measure' => 'weight', 'min_kg' => 40, 'max_kg' => 205],
            'visible_when' => [['field' => 'health_goals', 'operator' => 'contains', 'value' => 'weight-management']],
        ]);

        $flags = $this->quiz->steps()->create(['slug' => 'flags', 'name' => 'Flags', 'position' => 3, 'is_active' => true]);
        $flagQ = $flags->questions()->create([
            'slug' => 'flags', 'kind' => QuizQuestionKind::MultiSelect, 'prompt' => 'Flag?',
            'position' => 1, 'is_active' => true, 'is_required' => false,
        ]);
        $flagQ->options()->create(['value' => 'liver', 'label' => 'Liver', 'position' => 1]);
        $flagQ->options()->create(['value' => 'none', 'label' => 'None', 'is_exclusive' => true, 'position' => 2]);
    }

    private function payload(array $answers, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Andrew',
            'last_name' => 'Cappello',
            'email' => 'a@example.com',
            'email_consent' => true,
            'quiz_slug' => 'intake',
            'quiz_answers' => $answers,
        ], $overrides);
    }

    public function test_a_completed_quiz_becomes_a_lead_carrying_its_answers(): void
    {
        $response = $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['weight-management'],
            'weight' => 84,
            'goal_weight' => 75,
        ], ['age' => 45]))->assertCreated();

        $lead = Lead::firstWhere('email', 'a@example.com');

        $this->assertSame($this->quiz->id, $lead->quiz_id);
        $this->assertNotNull($lead->quiz_completed_at, 'quiz_completed_at is the flag separating funnel leads from cart leads');
        $this->assertSame(45, $lead->age);
        $this->assertSame(['weight-management'], $lead->quiz_answers['health_goals']);
        $this->assertSame(75, $lead->quiz_answers['goal_weight']);

        $response->assertJsonPath('data.quiz.answers.weight', 84);
    }

    public function test_an_answer_to_a_question_that_was_never_asked_is_dropped(): void
    {
        // goal_weight is only visible when weight-management was picked. This
        // submission did not pick it, so the answer is stale state at best —
        // and at worst a value the visitor never gave, which the report would
        // otherwise quote back at them.
        $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['sleep'],
            'weight' => 84,
            'goal_weight' => 60,
        ]))->assertCreated();

        $answers = Lead::firstWhere('email', 'a@example.com')->quiz_answers;

        $this->assertArrayNotHasKey('goal_weight', $answers);
        $this->assertSame(84, $answers['weight']);
    }

    public function test_a_missing_required_answer_is_rejected_with_its_own_field_error(): void
    {
        $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['sleep'],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quiz_answers.weight');
    }

    public function test_a_required_answer_hidden_by_branching_is_not_demanded(): void
    {
        // The inverse of the test above, and the reason validation walks the
        // conditions rather than the required list: a question the visitor
        // never saw must not block their submission.
        $this->quiz->questions()->where('slug', 'goal_weight')->update(['is_required' => true]);

        $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['sleep'],
            'weight' => 84,
        ]))->assertCreated();
    }

    public function test_a_value_outside_the_authored_bounds_is_rejected(): void
    {
        $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['sleep'],
            'weight' => 900,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quiz_answers.weight');
    }

    public function test_an_option_that_is_not_offered_is_rejected(): void
    {
        $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['sleep'],
            'weight' => 84,
            'flags' => ['liver', 'not-a-real-flag'],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quiz_answers.flags');
    }

    public function test_a_goal_withdrawn_from_the_quiz_is_not_an_acceptable_answer(): void
    {
        // The reserved kind reads the goals table at validation time too, so
        // withdrawing a goal closes it as an answer without any edit here.
        HealthGoal::where('slug', 'sleep')->update(['show_in_quiz' => false]);

        $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['sleep'],
            'weight' => 84,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quiz_answers.health_goals');
    }

    public function test_an_unknown_question_key_is_ignored_rather_than_failing_the_submission(): void
    {
        // A quiz edited between page load and submit is normal, not an attack.
        // Losing a real lead over a retired key would be the worse outcome.
        $this->postJson('/api/v1/leads', $this->payload([
            'health_goals' => ['sleep'],
            'weight' => 84,
            'question_that_was_retired' => 'x',
        ]))->assertCreated();

        $this->assertArrayNotHasKey(
            'question_that_was_retired',
            Lead::firstWhere('email', 'a@example.com')->quiz_answers
        );
    }

    public function test_attribution_is_stored_when_sent(): void
    {
        // Every UTM column has existed for months and been null throughout,
        // because nothing populated them.
        $this->postJson('/api/v1/leads', $this->payload(
            ['health_goals' => ['sleep'], 'weight' => 84],
            ['utm_source' => 'meta', 'utm_campaign' => 'q3-fatloss', 'landing_url' => 'https://atlasprotocol.com/quiz?utm_source=meta']
        ))->assertCreated();

        $lead = Lead::firstWhere('email', 'a@example.com');

        $this->assertSame('meta', $lead->utm_source);
        $this->assertSame('q3-fatloss', $lead->utm_campaign);
    }

    public function test_a_checkout_lead_without_a_quiz_still_works_unchanged(): void
    {
        // The regression that matters most here: checkout posts to this same
        // endpoint and knows nothing about quizzes.
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Cart', 'last_name' => 'Buyer', 'email' => 'cart@example.com',
        ])->assertCreated();

        $lead = Lead::firstWhere('email', 'cart@example.com');

        $this->assertNull($lead->quiz_id);
        $this->assertNull($lead->quiz_completed_at);
    }
}
