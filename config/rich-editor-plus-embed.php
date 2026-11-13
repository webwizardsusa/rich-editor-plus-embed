<?php

return [
    /*
     * The cache store oEmbed responses are kept in (null for the default store), how long a response is kept, and how
     * long a failed lookup is remembered before it is tried again.
     */
    'cache' => [
        'store' => env('RICH_EDITOR_PLUS_EMBED_CACHE_STORE'),
        'ttl' => 60 * 60 * 24 * 7,
        'failure_ttl' => 60 * 10,
    ],

    /*
     * Seconds to wait for a provider's oEmbed endpoint.
     */
    'timeout' => 3,

    /*
     * The `maxwidth` sent to providers.
     */
    'max_width' => 800,
];
