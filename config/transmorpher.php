<?php

use App\Facades\InterventionConverterFacade;
use App\Facades\InterventionTransmorpherFacade;

return [
    /*
   |--------------------------------------------------------------------------
   | Storage Disks
   |--------------------------------------------------------------------------
   |
   | The disks on which the Transmorpher operates.
   |
   */

    'disks' => [
        'originals' => 'imageOriginals',
        'imageDerivatives' => 'imageDerivatives'
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

    'transmorpher' => InterventionTransmorpherFacade::class,

    /*
    |--------------------------------------------------------------------------
    | Format Converters
    |--------------------------------------------------------------------------
    |
    | The Format Converters which are used for applying format conversions.
    |
    | Available Converters:
    | - InterventionConverter(based on Intervention Image)
    |
    */

    'converters' => [
        'jpg' => InterventionConverterFacade::class,
        'png' => InterventionConverterFacade::class,
        'gif' => InterventionConverterFacade::class,
        'webp' => InterventionConverterFacade::class,
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
