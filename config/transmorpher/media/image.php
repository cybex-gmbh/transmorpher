<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Handler
    |--------------------------------------------------------------------------
    |
    | The class which is responsible for handling media specific actions, such as validation rules or handling the newly saved file.
    | The class must implement MediaHandlerInterface.
    |
    | These are defined through the `config/transmorpher/classes/handler/media/image` files.
    |
    | You can choose from:
    | - default
    |
    */
    'handler' => env('TRANSMORPHER_IMAGE_HANDLER', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Transformer
    |--------------------------------------------------------------------------
    |
    | The class which is used for applying transformations.
    | The class must implement TransformInterface.
    |
    | These are defined through the `config/transmorpher/classes/image/transformer` files.
    |
    | Available transformers:
    | -  intervention (based on Intervention Image)
    |
    */
    'transformer' => env('TRANSMORPHER_IMAGE_TRANSFORMER', 'intervention'),

    /*
    |--------------------------------------------------------------------------
    | Converter
    |--------------------------------------------------------------------------
    |
    | The classes which are used for applying format conversions.
    | The classes must implement ConvertInterface.
    |
    | These are defined through the `config/transmorpher/classes/image/converter` files.
    |
    | Available Converters:
    | - intervention (based on Intervention Image)
    |
    */
    'converter' => [
        'jpg' => env('TRANSMORPHER_IMAGE_CONVERTER_JPG', 'intervention'),
        'png' => env('TRANSMORPHER_IMAGE_CONVERTER_PNG', 'intervention'),
        'gif' => env('TRANSMORPHER_IMAGE_CONVERTER_GIF', 'intervention'),
        'webp' => env('TRANSMORPHER_IMAGE_CONVERTER_WEBP', 'intervention'),
    ],

];
