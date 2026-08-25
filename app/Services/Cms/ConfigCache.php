<?php

namespace App\Services\Cms;

use Illuminate\Support\Facades\Cache;

/**
 * Invalidating the public `/config` bundle — BOTH SIDES OF IT, in one call.
 *
 * There are two caches between an operator saving a setting and a visitor
 * seeing it, and every settings action knew about only one:
 *
 *   1. This backend's own `api.v1.config` entry, which the API layer reads.
 *   2. The decoupled frontend's fetch cache, which holds the response for
 *      API_REVALIDATE seconds (300 in production) unless it is told otherwise.
 *
 * All seven Update*SettingsAction classes cleared (1) and none told (2), so a
 * palette retune, a logo swap or a phone-number correction sat invisible for
 * up to five minutes while the admin reported success. It was reported as a
 * caching mystery rather than a bug, which is exactly how this kind of split
 * survives.
 *
 * Keeping the pair in one method is the point. Two call sites that must always
 * fire together will eventually not, and the failure is silent on the side
 * nobody is looking at.
 *
 * The tag is `config` and deliberately NOT the broad `cms` tag: settings feed
 * `/config` alone, and over-purging every cached page to publish a colour
 * change would throw away the whole ISR window for nothing. See lib/api.js in
 * atlas-protocol-web, where getConfig() tags its fetch `["cms", "config"]`.
 */
final class ConfigCache
{
    public const KEY = 'api.v1.config';

    /** The frontend cache tag carried by every /config fetch. */
    public const TAG = 'config';

    public static function invalidate(): void
    {
        Cache::forget(self::KEY);

        // Queued and coalesced: a save that touches several settings still
        // sends one job, and a deployment with no frontend URL configured
        // no-ops rather than failing the save.
        app(FrontendRevalidator::class)->tagsChanged(self::TAG);
    }
}
