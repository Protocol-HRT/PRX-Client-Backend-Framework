<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public payload cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long page / layout / menu API payloads stay cached. Invalidation is
    | instant on save via the versioned CmsCache namespace; this TTL is only
    | the backstop for stale versions aging out.
    |
    */

    'cache_ttl' => env('CMS_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Decoupled frontend revalidation
    |--------------------------------------------------------------------------
    |
    | Bumping the CmsCache version makes THIS app's API payloads fresh
    | instantly, but a decoupled frontend caches its own renders and would
    | keep serving them until its ISR window expires. When a revalidate URL
    | is configured, CMS writes POST the affected cache tags to it so the
    | frontend can purge on demand.
    |
    | Leave `url` empty to disable — the backend ships without assuming any
    | particular frontend exists. Multiple frontends may be served from one
    | backend: `url` accepts a comma-separated list.
    |
    */

    'frontend' => [
        'revalidate_url' => env('CMS_FRONTEND_REVALIDATE_URL'),
        'revalidate_secret' => env('CMS_FRONTEND_REVALIDATE_SECRET'),
        'revalidate_timeout' => (int) env('CMS_FRONTEND_REVALIDATE_TIMEOUT', 5),

        /*
         * The frontend's PUBLIC origin, for links this backend puts in an
         * email. Separate from revalidate_url on purpose: that one points at
         * 127.0.0.1 because both apps sit on one box and the request must
         * never leave it, whereas a link in an email has to work in somebody
         * else's browser. Using the revalidate host for both would email a
         * recipient a localhost URL.
         *
         * Falls back to APP_URL, which is the admin — wrong, but visibly
         * wrong, rather than a link to nowhere.
         */
        'url' => env('CMS_FRONTEND_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page revisions
    |--------------------------------------------------------------------------
    */

    'revisions' => [
        'keep' => env('CMS_REVISIONS_KEEP', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Menus
    |--------------------------------------------------------------------------
    */

    'menu' => [
        'max_depth' => 3,
    ],

];
