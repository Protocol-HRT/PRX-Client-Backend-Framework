<?php

namespace App\Observers;

use App\Services\Cms\CmsCache;

/**
 * Attached to every CMS content model (Page, PageSection — later GlobalSection,
 * Menu, MenuItem, RegionItem, FlexibleSectionType). Any write moves the CMS
 * cache namespace forward so public payloads refresh immediately.
 */
class CmsCacheObserver
{
    public function __construct(private readonly CmsCache $cache) {}

    public function saved(): void
    {
        $this->cache->bump();
    }

    public function deleted(): void
    {
        $this->cache->bump();
    }

    public function restored(): void
    {
        $this->cache->bump();
    }
}
