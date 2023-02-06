<?php

use App\Classes\Intervention\Convert;
use App\Classes\Intervention\Transform;

return [
    /*
    |--------------------------------------------------------------------------
    | Development mode
    |--------------------------------------------------------------------------
    |
    | When this is set to true, derivatives will always be regenerated.
    | Only needs to be considered when the option to store derivatives is also true.
    |
    */
    'dev_mode' => env('TRANSMORPHER_DEV_MODE'),

    /*
    |--------------------------------------------------------------------------
    | Store derivatives
    |--------------------------------------------------------------------------
    |
    | When this is set to false, derivatives won't be saved to disk.
    | Only applies to image derivatives, since video derivatives have to be saved.
    |
    */
    'store_derivatives' => env('TRANSMORPHER_STORE_DERIVATIVES'),


    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    |
    | The disks on which the Transmorpher operates.
    |
    */

    'disks' => [
        'originals' => env('TRANSMORPHER_DISK_MAIN'),
        'imageDerivatives' =>  env('TRANSMORPHER_DISK_IMAGE_DERIVATIVES'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transform class
    |--------------------------------------------------------------------------
    |
    | The Transform class which is used for applying transformations.
    |
    | Available Transform classes:
    | -  Intervention\Transform (based on Intervention Image)
    |
    */

    'transform_class' => Transform::class,

    /*
    |--------------------------------------------------------------------------
    | Convert classes
    |--------------------------------------------------------------------------
    |
    | The Convert classes which are used for applying format conversions.
    |
    | Available Convert classes:
    | - Intervention\Convert (based on Intervention Image)
    |
    */

    'convert_classes' => [
        'jpg' => Convert::class,
        'png' => Convert::class,
        'gif' => Convert::class,
        'webp' => Convert::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Information
    |--------------------------------------------------------------------------
    |
    | Information which is needed for using AWS as CDN provider.
    |
    */

    'aws' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'cloudfront_distribution_id' => env('AWS_CLOUDFRONT_DISTRIBUTION_ID')
    ]
];
