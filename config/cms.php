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
