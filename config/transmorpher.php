<?php

use App\Helpers\InterventionTransmorpher\InterventionTransmorpher;
use App\Helpers\InterventionTransmorpher\InterventionConverter;

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
   | Transmorpher
   |--------------------------------------------------------------------------
   |
   | The Transmorpher which is used for applying transformations.
   |
   | Available Transmorphers:
   | - InterventionTransmorpher (based on Intervention Image)
   |
   */

    'transmorpher' => InterventionTransmorpher::class,

    /*
    |--------------------------------------------------------------------------
    | Format Converters
    |--------------------------------------------------------------------------
    |
    | The Format Converters which are used for applying format conversions.
    |
    | Available Converters:
    | - InterventionConverter (based on Intervention Image)
    |
    */

    'converters' => [
        'jpg' => InterventionConverter::class,
        'png' => InterventionConverter::class,
        'gif' => InterventionConverter::class,
        'webp' => InterventionConverter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Information
    |--------------------------------------------------------------------------
    |
    | Information which is needed for using AWS as Storage and CDN provider.
    |
    */

    'aws' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'cloudfront_distribution_id' => env('AWS_CLOUDFRONT_DISTRIBUTION_ID')
    ]
];
