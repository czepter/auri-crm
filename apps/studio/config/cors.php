<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | G-09: apps/gallery (a separate origin — its own Astro server) is the
    | first browser JavaScript to call this app directly, from the lightbox's
    | favorite toggle. guest_id travels as a request field, not a cookie (see
    | AssetFavoriteController's own doc comment on why), so this needs no
    | credentialed-CORS ceremony — no `SameSite=None`, no per-origin allow
    | list, no `supports_credentials`. Every api/* route already answers with
    | data that carries no per-origin secret (a gallery's own gate already
    | decides who reaches it before any of this runs), so any origin may ask.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
