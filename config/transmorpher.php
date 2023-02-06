<?php

use App\Classes\Intervention\Converter;
use App\Classes\Intervention\Transmorpher;
use App\Classes\Transcode;
use App\Helpers\CloudFrontHelper;

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
        'videoDerivatives' =>  env('TRANSMORPHER_DISK_VIDEO_DERIVATIVES'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transmorpher
    |--------------------------------------------------------------------------
    |
    | The Transmorpher which is used for applying transformations.
    |
    | Available Transmorphers:
    | -  Intervention\Transmorpher (based on Intervention Image)
    |
    */

    'transmorpher_class' => Transmorpher::class,

    /*
    |--------------------------------------------------------------------------
    | Format Converters
    |--------------------------------------------------------------------------
    |
    | The Format Converters which are used for applying format conversions.
    |
    | Available Converters:
    | - Intervention\Converter (based on Intervention Image)
    |
    */

    'converter_classes' => [
        'jpg' => Converter::class,
        'png' => Converter::class,
        'gif' => Converter::class,
        'webp' => Converter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transcode Class
    |--------------------------------------------------------------------------
    |
    | The class which is used for transcoding videos.
    |
    | Available Transcode classes:
    | -  Transcode (uses FFmpeg and Laravel Queue for transcoding)
    |
    */

    'transcode_class' => Transcode::class,

    /*
    |--------------------------------------------------------------------------
    | Representations
    |--------------------------------------------------------------------------
    |
    | The representations which are created when transcoding a video.
    |
    | You can choose from:
    | 144, 240, 360, 480, 720, 1080, 1440, 2160
    |
    */
    'representations' => [
        720,
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Helper
    |--------------------------------------------------------------------------
    |
    | Helper for creating CDN invalidations.
    |
    */

    'cdn_helper' => CloudFrontHelper::class,

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
    ],

    /*
    |--------------------------------------------------------------------------
    | Signing Keypair
    |--------------------------------------------------------------------------
    |
    | The keypair which is used for signing requests to the client package.
    |
    */

    'signing_keypair' => env('TRANSMORPHER_SIGNING_KEYPAIR')
];
