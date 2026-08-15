<?php

namespace App\Services\Cms;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache namespace for all public CMS payloads (pages, layout, menus).
 *
 * Keys embed a monotonically increasing version — bump() is called by
 * CmsCacheObserver on any CMS content save, so admins see changes instantly
 * while public traffic keeps hitting cached responses. Stale keys are never
 * deleted; they simply age out via their TTL.
 */
class CmsCache
{
    private const VERSION_KEY = 'cms:version';

    public function key(string $suffix): string
    {
        return 'cms.v'.$this->version().'.'.$suffix;
    }

    /**
     * Remember a payload under the current CMS content version.
     */
    public function remember(string $suffix, Closure $callback): mixed
    {
        return Cache::remember(
            $this->key($suffix),
            config('cms.cache_ttl', 300),
            $callback,
        );
    }

    public function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn (): int => 1);
    }

    /**
     * Invalidate every CMS payload by moving the namespace forward.
     */
    public function bump(): void
    {
        if (Cache::has(self::VERSION_KEY)) {
            Cache::increment(self::VERSION_KEY);

            return;
        }

        Cache::forever(self::VERSION_KEY, 2);
    }
}
