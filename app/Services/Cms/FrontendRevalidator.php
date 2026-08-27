<?php

namespace App\Services\Cms;

use App\Jobs\Cms\RevalidateFrontendJob;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Content\FaqCategory;
use App\Models\Content\FaqItem;
use App\Models\Kb\Compound;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;

/**
 * Translates a CMS write into the cache tags a decoupled frontend must
 * purge, and ships them once per request.
 *
 * Tags, not URLs: this app names entities (`page:faq`) and the frontend owns
 * the routes those entities live at. Sending paths would couple the backend
 * to one frontend's routing.
 *
 * Registered as a singleton so a single admin save — which fires Page::saved
 * plus one PageSection::saved per section — coalesces into ONE queued job
 * instead of a dozen. Tags accumulate during the request and flush on
 * terminate.
 */
class FrontendRevalidator
{
    /** Purges every content payload; used when a write's blast radius is broad. */
    private const TAG_ALL = 'cms';

    /** @var array<string, true> */
    private array $tags = [];

    private bool $flushRegistered = false;

    public function __construct(private readonly Application $app) {}

    public function enabled(): bool
    {
        return filled(config('cms.frontend.revalidate_url'))
            && filled(config('cms.frontend.revalidate_secret'));
    }

    /**
     * Queue the tags for a model that was just written.
     */
    public function modelChanged(Model $model): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach ($this->tagsFor($model) as $tag) {
            $this->tags[$tag] = true;
        }

        $this->registerFlush();
    }

    /**
     * Queue tags for a write that is NOT an Eloquent model.
     *
     * Settings are the reason this exists: they live in the `settings` table
     * as a spatie payload, never pass through an observer, and so were
     * invisible to modelChanged(). Every settings save cleared the backend's
     * own `api.v1.config` cache and told the frontend nothing, which left a
     * palette or brand edit waiting out the full ISR window while the admin
     * insisted it had saved.
     *
     * Prefer ConfigCache::invalidate() over calling this directly for config
     * writes — it pairs this with the backend cache clear so the two cannot
     * drift apart again.
     */
    public function tagsChanged(string ...$tags): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach ($tags as $tag) {
            if (filled($tag)) {
                $this->tags[$tag] = true;
            }
        }

        $this->registerFlush();
    }

    /**
     * Send whatever has accumulated. Safe to call directly (tests, commands).
     */
    public function flush(): void
    {
        if ($this->tags === [] || ! $this->enabled()) {
            return;
        }

        $tags = array_keys($this->tags);
        $this->tags = [];

        RevalidateFrontendJob::dispatch($tags);
    }

    /**
     * Cache tags a write to this model invalidates.
     *
     * Anything whose blast radius isn't a single addressable entity falls
     * back to TAG_ALL — a global section or a flexible type can appear on
     * any page, and FAQ rows are inlined into cached page payloads by the
     * faq-categories section. Over-purging is cheap; under-purging shows
     * operators stale content.
     *
     * @return list<string>
     */
    private function tagsFor(Model $model): array
    {
        return match (true) {
            $model instanceof Page => array_values(array_filter([
                self::TAG_ALL,
                $model->slug ? 'page:'.$model->slug : null,
            ])),

            $model instanceof PageSection => array_values(array_filter([
                self::TAG_ALL,
                ($slug = $model->page?->slug) ? 'page:'.$slug : null,
            ])),

            $model instanceof Menu => array_values(array_filter([
                self::TAG_ALL,
                'layout',
                $model->slug ? 'menu:'.$model->slug : null,
            ])),

            $model instanceof MenuItem => array_values(array_filter([
                self::TAG_ALL,
                'layout',
                ($menuSlug = $model->menu?->slug) ? 'menu:'.$menuSlug : null,
            ])),

            $model instanceof FaqCategory,
            $model instanceof FaqItem => [self::TAG_ALL],

            // `kb` is the broad tag the index page carries; `kb:{slug}` is the
            // monograph itself. Both are sent because publishing a compound
            // changes the listing as well as the detail page, and the frontend
            // caches them under separate tags.
            $model instanceof Compound => array_values(array_filter([
                self::TAG_ALL,
                'kb',
                $model->slug ? 'kb:'.$model->slug : null,
            ])),

            default => [self::TAG_ALL],
        };
    }

    /**
     * Flush at end of request so one save sends one job. Registered lazily —
     * a request that touches no CMS content registers no callback.
     *
     * Two hooks, because neither covers everything:
     *   - terminating() fires after an HTTP response and after each queued
     *     job, which is what a long-running Horizon worker needs (a shutdown
     *     hook there would hold tags until the worker itself stopped).
     *   - register_shutdown_function() covers `artisan tinker`, which exits
     *     without running terminating callbacks — that is the path the
     *     deployment fill scripts use, and they would otherwise never
     *     revalidate.
     *
     * flush() empties the tag set, so whichever fires first wins and the
     * other is a no-op. Registering both is safe.
     */
    private function registerFlush(): void
    {
        if ($this->flushRegistered) {
            return;
        }

        $this->flushRegistered = true;

        $this->app->terminating(function (): void {
            $this->flushRegistered = false;
            $this->flush();
        });

        // Console only, and never under test: by the time a shutdown handler
        // runs the container may already be torn down, and dispatching needs
        // it. Tests reset the app between cases, so this would fatal at the
        // end of the run; they exercise flush() directly instead.
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            register_shutdown_function(function (): void {
                $this->flushRegistered = false;

                try {
                    $this->flush();
                } catch (\Throwable $e) {
                    // Losing a revalidation is survivable — the frontend's own
                    // TTL still expires. Killing the process at shutdown is not.
                    report($e);
                }
            });
        }
    }
}
