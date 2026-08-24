<?php

namespace App\Observers;

use App\Services\Cms\CmsCache;
use App\Services\Cms\FrontendRevalidator;
use Illuminate\Database\Eloquent\Model;

/**
 * Attached to every CMS content model (Page, PageSection, GlobalSection,
 * Menu, MenuItem, RegionItem, FlexibleSectionType, FaqCategory, FaqItem).
 *
 * Two layers of invalidation, because there are two caches:
 *   1. CmsCache::bump() — moves THIS app's public payload namespace forward,
 *      so the API is fresh on the next request.
 *   2. FrontendRevalidator — carries the same invalidation across the repo
 *      boundary to the decoupled frontend, which caches its own renders and
 *      would otherwise serve them until its ISR window expired. No-ops when
 *      no frontend revalidate URL is configured.
 */
class CmsCacheObserver
{
    public function __construct(
        private readonly CmsCache $cache,
        private readonly FrontendRevalidator $revalidator,
    ) {}

    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $this->cache->bump();
        $this->revalidator->modelChanged($model);
    }
}
