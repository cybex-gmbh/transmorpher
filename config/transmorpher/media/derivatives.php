<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Store derivatives
    |--------------------------------------------------------------------------
    |
    | When this is set to false, derivatives won't be saved to disk.
    | Only applies to image and document derivatives, since video derivatives have to be saved.
    |
    */
    'store' => env('TRANSMORPHER_STORE_DERIVATIVES', true),

    'cache' => [
        'invalidation' => [
            'file' => [
                /*
                |--------------------------------------------------------------------------
                | Cache Invalidation Counter File Path
                |--------------------------------------------------------------------------
                |
                | The path to a file on the originals disk that stores the cache invalidation counter.
                |
                */
                'path' => env('CACHE_INVALIDATION_COUNTER_FILE_PATH', 'cacheInvalidationCounter'),
            ]
        ]
    ]
];
