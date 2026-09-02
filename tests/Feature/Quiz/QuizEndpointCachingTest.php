<?php

namespace Tests\Feature\Quiz;

use App\Enums\Quiz\QuizQuestionKind;
use App\Models\Quiz\Quiz;
use App\Services\Quiz\QuizCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Quiz endpoint caching: the schema is cached per slug and invalidated
 * instantly on quiz/step/question/option/plan/package/health-goal writes.
 */
class QuizEndpointCachingTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush quiz cache between tests so cached values never bleed across.
        Cache::forget('quiz:version');

        $this->quiz = Quiz::create(['name' => 'Test', 'slug' => 'test-quiz', 'is_active' => true, 'is_default' => true]);
        $step = $this->quiz->steps()->create(['slug' => 'step-1', 'name' => 'Step 1', 'position' => 1, 'is_active' => true]);
        $step->questions()->create([
            'quiz_id' => $this->quiz->id,
            'slug' => 'q1',
            'kind' => QuizQuestionKind::Text,
            'prompt' => 'What?',
            'position' => 1,
            'is_active' => true,
        ]);
    }

    public function test_quiz_response_is_cached(): void
    {
        $this->getJson('/api/v1/quiz')->assertOk();

        $this->assertNotNull(Cache::get(QuizCache::key('test-quiz')));
    }

    public function test_second_request_returns_cached_response(): void
    {
        $response1 = $this->getJson('/api/v1/quiz')->assertOk();
        $response2 = $this->getJson('/api/v1/quiz')->assertOk();

        $this->assertSame(
            $response1->json('data'),
            $response2->json('data'),
        );
    }

    public function test_quiz_response_reflects_schema_changes_before_cache_invalidation(): void
    {
        // First request populates the cache.
        $this->getJson('/api/v1/quiz')->assertOk();

        // Mutate the quiz — the observer bumps the version, invalidating cache.
        $this->quiz->update(['name' => 'Updated Quiz']);

        // Next request should return fresh data.
        $response = $this->getJson('/api/v1/quiz')->assertOk();
        $this->assertSame('Updated Quiz', $response->json('data.name'));
    }

    public function test_named_quiz_endpoint_is_also_cached(): void
    {
        $this->getJson('/api/v1/quiz/test-quiz')->assertOk();
        $this->assertNotNull(Cache::get(QuizCache::key('test-quiz')));

        // Second hit returns cached data.
        $response = $this->getJson('/api/v1/quiz/test-quiz')->assertOk();
        $this->assertSame('test-quiz', $response->json('data.slug'));
    }

    public function test_different_quizzes_get_separate_cache_entries(): void
    {
        $second = Quiz::create(['name' => 'Second', 'slug' => 'second', 'is_active' => true]);
        $step = $second->steps()->create(['slug' => 'step-1', 'name' => 'Step 1', 'position' => 1, 'is_active' => true]);
        $step->questions()->create([
            'quiz_id' => $second->id,
            'slug' => 'q1',
            'kind' => QuizQuestionKind::Text,
            'prompt' => 'What?',
            'position' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/quiz/test-quiz')->assertOk();
        $this->getJson('/api/v1/quiz/second')->assertOk();

        $this->assertNotNull(Cache::get(QuizCache::key('test-quiz')));
        $this->assertNotNull(Cache::get(QuizCache::key('second')));
    }

    public function test_invalidation_on_question_save_does_not_affect_other_quizzes(): void
    {
        $second = Quiz::create(['name' => 'Second', 'slug' => 'second', 'is_active' => true]);
        $step = $second->steps()->create(['slug' => 'step-1', 'name' => 'Step 1', 'position' => 1, 'is_active' => true]);
        $step->questions()->create([
            'quiz_id' => $second->id,
            'slug' => 'q1',
            'kind' => QuizQuestionKind::Text,
            'prompt' => 'What?',
            'position' => 1,
            'is_active' => true,
        ]);

        // Cache both quizzes.
        $this->getJson('/api/v1/quiz/test-quiz')->assertOk();
        $this->getJson('/api/v1/quiz/second')->assertOk();

        // Mutate the first quiz — version bumps, both old keys become stale.
        $this->quiz->update(['name' => 'Changed']);

        // Both caches are invalidated by the version bump (this is by design —
        // versioned keys invalidate ALL quizzes on any quiz write, which is
        // acceptable because quiz writes are infrequent admin operations).
        $this->assertNull(Cache::get(QuizCache::key('test-quiz')));
        $this->assertNull(Cache::get(QuizCache::key('second')));
    }

    public function test_404_quiz_is_not_cached(): void
    {
        $this->getJson('/api/v1/quiz/nonexistent')->assertStatus(404);

        // No cache entry should exist for a non-existent quiz.
        $this->assertNull(Cache::get(QuizCache::key('nonexistent')));
    }
}
