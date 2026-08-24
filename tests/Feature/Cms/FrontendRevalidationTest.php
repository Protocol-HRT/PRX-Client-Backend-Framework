<?php

namespace Tests\Feature\Cms;

use App\Jobs\Cms\RevalidateFrontendJob;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Content\FaqCategory;
use App\Models\Page;
use App\Models\PageSection;
use App\Services\Cms\FrontendRevalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * CMS writes tell the decoupled frontend which cache tags to purge, so admin
 * edits appear immediately rather than after the frontend's ISR window.
 */
class FrontendRevalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cms.frontend.revalidate_url' => 'https://frontend.test/api/revalidate',
            'cms.frontend.revalidate_secret' => 'test-secret',
        ]);
    }

    private function revalidator(): FrontendRevalidator
    {
        return app(FrontendRevalidator::class);
    }

    /** @return list<string> */
    private function tagsFromDispatchedJob(): array
    {
        $tags = [];

        Queue::assertPushed(RevalidateFrontendJob::class, function (RevalidateFrontendJob $job) use (&$tags): bool {
            $tags = (fn (): array => $this->tags)->call($job);

            return true;
        });

        return $tags;
    }

    public function test_disabled_when_no_frontend_url_is_configured(): void
    {
        config(['cms.frontend.revalidate_url' => null]);

        $this->assertFalse($this->revalidator()->enabled());
    }

    public function test_disabled_when_no_secret_is_configured(): void
    {
        config(['cms.frontend.revalidate_secret' => null]);

        $this->assertFalse($this->revalidator()->enabled());
    }

    public function test_no_job_is_dispatched_when_disabled(): void
    {
        Queue::fake();
        config(['cms.frontend.revalidate_url' => null]);

        Page::factory()->create(['slug' => 'about']);
        $this->revalidator()->flush();

        Queue::assertNothingPushed();
    }

    public function test_page_save_tags_that_page(): void
    {
        Queue::fake();

        Page::factory()->create(['slug' => 'about']);
        $this->revalidator()->flush();

        $this->assertContains('page:about', $this->tagsFromDispatchedJob());
    }

    public function test_page_section_save_tags_its_parent_page(): void
    {
        Queue::fake();

        $page = Page::factory()->create(['slug' => 'landing']);
        PageSection::factory()->create(['page_id' => $page->id, 'type' => 'faq']);
        $this->revalidator()->flush();

        $this->assertContains('page:landing', $this->tagsFromDispatchedJob());
    }

    public function test_menu_item_save_tags_its_menu_and_the_layout(): void
    {
        Queue::fake();

        $menu = Menu::factory()->create(['slug' => 'main-nav']);
        MenuItem::factory()->create(['menu_id' => $menu->id]);
        $this->revalidator()->flush();

        $tags = $this->tagsFromDispatchedJob();

        $this->assertContains('menu:main-nav', $tags);
        $this->assertContains('layout', $tags);
    }

    public function test_faq_write_falls_back_to_the_broad_tag(): void
    {
        Queue::fake();

        FaqCategory::factory()->create();
        $this->revalidator()->flush();

        $this->assertContains('cms', $this->tagsFromDispatchedJob());
    }

    public function test_many_writes_coalesce_into_one_job(): void
    {
        Queue::fake();

        $page = Page::factory()->create(['slug' => 'home']);
        foreach (range(1, 5) as $position) {
            PageSection::factory()->create([
                'page_id' => $page->id,
                'type' => 'faq',
                'position' => $position,
            ]);
        }

        $this->revalidator()->flush();

        // One admin save fires many model events; the frontend should be
        // asked to revalidate once, not once per section.
        Queue::assertPushed(RevalidateFrontendJob::class, 1);
    }

    public function test_flushing_twice_does_not_resend(): void
    {
        Queue::fake();

        Page::factory()->create(['slug' => 'about']);
        $this->revalidator()->flush();
        $this->revalidator()->flush();

        Queue::assertPushed(RevalidateFrontendJob::class, 1);
    }

    public function test_job_posts_tags_with_the_shared_secret(): void
    {
        Http::fake(['frontend.test/*' => Http::response(['revalidated' => ['cms']])]);

        (new RevalidateFrontendJob(['cms', 'page:about']))->handle();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://frontend.test/api/revalidate'
                && $request->header('x-revalidate-secret') === ['test-secret']
                && $request['tags'] === ['cms', 'page:about'];
        });
    }

    public function test_job_posts_to_every_configured_frontend(): void
    {
        config(['cms.frontend.revalidate_url' => 'https://a.test/api/revalidate, https://b.test/api/revalidate']);
        Http::fake(['*' => Http::response(['revalidated' => ['cms']])]);

        (new RevalidateFrontendJob(['cms']))->handle();

        Http::assertSentCount(2);
    }

    public function test_job_throws_so_the_queue_retries_when_the_frontend_errors(): void
    {
        Http::fake(['frontend.test/*' => Http::response('nope', 500)]);

        $this->expectException(RequestException::class);

        (new RevalidateFrontendJob(['cms']))->handle();
    }
}
