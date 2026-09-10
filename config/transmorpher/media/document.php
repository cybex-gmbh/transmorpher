<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Handler
    |--------------------------------------------------------------------------
    |
    | The class which is responsible for handling media specific actions, such as validation rules or handling the newly saved file.
    | The class must implement MediaHandlerInterface.
    |
    | These are defined through the `config/transmorpher/classes/handler/media/document` files.
    |
    | You can choose from:
    | - default
    |
    */
    'handler' => env('TRANSMORPHER_DOCUMENT_HANDLER', 'default'),

    'defaults' => [
        /*
        |--------------------------------------------------------------------------
        | Default PPI
        |--------------------------------------------------------------------------
        |
        | The PPI will be multiplied with the document dimensions, which results in the image resolution.
        |
        */
        'ppi' => env('TRANSMORPHER_DOCUMENT_DEFAULT_PPI', 300),
    ],

    'metadata' => [
        /*
        |--------------------------------------------------------------------------
        | Remove Metadata
        |--------------------------------------------------------------------------
        |
        | Defines whether the metadata of documents should be removed.
        |
        */
        'remove' => env('TRANSMORPHER_DOCUMENT_REMOVE_METADATA', false),
    ],
];
