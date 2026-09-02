<?php

namespace Tests\Feature\Quiz;

use App\Enums\CatalogStatus;
use App\Enums\Quiz\QuizQuestionKind;
use App\Jobs\Cms\RevalidateFrontendJob;
use App\Models\Catalog\Package;
use App\Models\Catalog\Plan;
use App\Models\Kb\HealthGoal;
use App\Models\Page;
use App\Models\Quiz\Quiz;
use App\Services\Cms\FrontendRevalidator;
use App\Services\Quiz\QuizCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use ReflectionProperty;
use Tests\TestCase;

class QuizCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure a clean version state for each test.
        Cache::forget('quiz:version');
    }

    public function test_remember_returns_callback_result_and_caches_it(): void
    {
        $callCount = 0;
        $result = QuizCache::remember('my-quiz', function () use (&$callCount): array {
            $callCount++;

            return ['steps' => []];
        });

        $this->assertSame(['steps' => []], $result);
        $this->assertSame(1, $callCount);

        // Second call should hit cache, not the callback.
        $result2 = QuizCache::remember('my-quiz', function () use (&$callCount): array {
            $callCount++;

            return ['steps' => ['changed']];
        });

        $this->assertSame(['steps' => []], $result2);
        $this->assertSame(1, $callCount);
    }

    public function test_different_slugs_get_separate_cache_entries(): void
    {
        QuizCache::remember('quiz-a', fn (): string => 'alpha');
        QuizCache::remember('quiz-b', fn (): string => 'bravo');

        $this->assertSame('alpha', Cache::get(QuizCache::key('quiz-a')));
        $this->assertSame('bravo', Cache::get(QuizCache::key('quiz-b')));
    }

    public function test_invalidate_bumps_the_version(): void
    {
        $v1 = QuizCache::version();
        QuizCache::invalidate();
        $v2 = QuizCache::version();

        $this->assertSame($v1 + 1, $v2);
    }

    public function test_invalidate_makes_old_cached_entries_unreachable(): void
    {
        QuizCache::remember('my-quiz', fn (): string => 'original');
        $this->assertSame('original', Cache::get(QuizCache::key('my-quiz')));

        QuizCache::invalidate();

        // The old key still exists in cache but the controller would now
        // read a different (non-existent) versioned key.
        $oldKey = 'api.v1.quiz.v'.($v1 = QuizCache::version() - 1).'.my-quiz';
        $newKey = QuizCache::key('my-quiz');

        $this->assertNull(Cache::get($newKey));
        // Old key is still there but unreachable via the current version.
        $this->assertSame('original', Cache::get($oldKey));
    }

    public function test_invalidate_revalidates_the_frontend_quiz_tag(): void
    {
        config()->set('cms.frontend.revalidate_url', 'https://frontend.test/api/revalidate');
        config()->set('cms.frontend.revalidate_secret', 'test-secret');

        Queue::fake();

        QuizCache::invalidate();
        app(FrontendRevalidator::class)->flush();

        Queue::assertPushed(
            RevalidateFrontendJob::class,
            function (RevalidateFrontendJob $job): bool {
                $tags = new ReflectionProperty($job, 'tags');
                $tags->setAccessible(true);

                return in_array(QuizCache::TAG, $tags->getValue($job), true);
            },
        );
    }

    public function test_invalidate_is_safe_when_no_frontend_is_configured(): void
    {
        config()->set('cms.frontend.revalidate_url', null);
        config()->set('cms.frontend.revalidate_secret', null);

        Queue::fake();

        QuizCache::invalidate();
        app(FrontendRevalidator::class)->flush();

        Queue::assertNothingPushed();
    }

    public function test_observer_invalidates_quiz_cache_when_quiz_is_saved(): void
    {
        config()->set('cms.frontend.revalidate_url', 'https://frontend.test/api/revalidate');
        config()->set('cms.frontend.revalidate_secret', 'test-secret');

        // Prime the cache.
        $quiz = Quiz::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'is_default' => true]);
        QuizCache::remember('test', fn (): string => 'cached');
        $this->assertNotNull(Cache::get(QuizCache::key('test')));

        Queue::fake();

        // Saving the quiz triggers CmsCacheObserver → QuizCache::invalidate().
        $quiz->update(['name' => 'Updated']);

        // The cache should now be invalidated.
        $this->assertNull(Cache::get(QuizCache::key('test')));
    }

    public function test_observer_invalidates_quiz_cache_when_plan_price_changes(): void
    {
        config()->set('cms.frontend.revalidate_url', 'https://frontend.test/api/revalidate');
        config()->set('cms.frontend.revalidate_secret', 'test-secret');

        $quiz = Quiz::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'is_default' => true]);
        QuizCache::remember('test', fn (): string => 'cached');
        $this->assertNotNull(Cache::get(QuizCache::key('test')));

        Queue::fake();

        $package = Package::factory()->create(['status' => CatalogStatus::Published, 'tier' => 'protocol']);
        Plan::factory()->create(['package_id' => $package->id, 'status' => CatalogStatus::Published, 'retail_price' => 100]);

        // Plan save triggers CmsCacheObserver → QuizCache::invalidate().
        // The plan factory create above already triggered it.
        $this->assertNull(Cache::get(QuizCache::key('test')));
    }

    public function test_observer_invalidates_quiz_cache_when_health_goal_changes(): void
    {
        config()->set('cms.frontend.revalidate_url', 'https://frontend.test/api/revalidate');
        config()->set('cms.frontend.revalidate_secret', 'test-secret');

        $quiz = Quiz::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'is_default' => true]);
        QuizCache::remember('test', fn (): string => 'cached');
        $this->assertNotNull(Cache::get(QuizCache::key('test')));

        Queue::fake();

        HealthGoal::create(['name' => 'New Goal', 'slug' => 'new-goal', 'show_in_quiz' => true]);

        $this->assertNull(Cache::get(QuizCache::key('test')));
    }

    public function test_observer_invalidates_quiz_cache_when_question_is_saved(): void
    {
        config()->set('cms.frontend.revalidate_url', 'https://frontend.test/api/revalidate');
        config()->set('cms.frontend.revalidate_secret', 'test-secret');

        $quiz = Quiz::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'is_default' => true]);
        $step = $quiz->steps()->create(['slug' => 'step-1', 'name' => 'Step 1', 'position' => 1, 'is_active' => true]);

        QuizCache::remember('test', fn (): string => 'cached');
        $this->assertNotNull(Cache::get(QuizCache::key('test')));

        Queue::fake();

        $step->questions()->create([
            'quiz_id' => $quiz->id,
            'slug' => 'q1',
            'kind' => QuizQuestionKind::Text,
            'prompt' => 'Question?',
            'position' => 1,
            'is_active' => true,
        ]);

        $this->assertNull(Cache::get(QuizCache::key('test')));
    }

    public function test_observer_does_not_invalidate_quiz_cache_for_unrelated_models(): void
    {
        config()->set('cms.frontend.revalidate_url', 'https://frontend.test/api/revalidate');
        config()->set('cms.frontend.revalidate_secret', 'test-secret');

        $quiz = Quiz::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'is_default' => true]);
        QuizCache::remember('test', fn (): string => 'cached');
        $this->assertNotNull(Cache::get(QuizCache::key('test')));

        Queue::fake();

        // A Page save should NOT invalidate quiz cache.
        Page::create(['title' => 'About', 'slug' => 'about']);

        $this->assertNotNull(Cache::get(QuizCache::key('test')));
    }
}
