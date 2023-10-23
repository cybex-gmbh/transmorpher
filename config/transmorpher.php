<?php

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
    'dev_mode' => env('TRANSMORPHER_DEV_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Store derivatives
    |--------------------------------------------------------------------------
    |
    | When this is set to false, derivatives won't be saved to disk.
    | Only applies to image derivatives, since video derivatives have to be saved.
    |
    */
    'store_derivatives' => env('TRANSMORPHER_STORE_DERIVATIVES', true),

    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    |
    | The disks on which the Transmorpher operates.
    |
    */
    'disks' => [
        'originals' => env('TRANSMORPHER_DISK_ORIGINALS', 'localOriginals'),
        'imageDerivatives' =>  env('TRANSMORPHER_DISK_IMAGE_DERIVATIVES', 'localImageDerivatives'),
        'videoDerivatives' =>  env('TRANSMORPHER_DISK_VIDEO_DERIVATIVES', 'localVideoDerivatives'),
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
    'transform_class' => App\Classes\Intervention\Transform::class,

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
        'jpg' => App\Classes\Intervention\Convert::class,
        'png' => App\Classes\Intervention\Convert::class,
        'gif' => App\Classes\Intervention\Convert::class,
        'webp' => App\Classes\Intervention\Convert::class,
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
    'transcode_class' => App\Classes\Transcode::class,

    /*
    |--------------------------------------------------------------------------
    | Video Codec
    |--------------------------------------------------------------------------
    |
    | The codec used when transcoding videos to HLS and DASH.
    | This does not affect the codec used for MP4, which is x264 since it's the only one supported by the PHP-FFmpeg package for MP4.
    |
    | You can choose from:
    | x264, hevc
    |
    */
    'video_codec' => 'x264',

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
        360, 480, 720, 1080, 1440, 2160
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Transcoding Parameters
    |--------------------------------------------------------------------------
    |
    | These parameters will be added to the FFmpeg transcoding command.
    |
    | -dn: omit data streams (e.g. timecodes). Transcoding sometimes failed when data streams were not omitted.
    | -map -0:t?: omit attachments (e.g. metadata files). Metadata should not be publicly available.
    | -sn: omit subtitles. Subtitles would need an encoder configuration for DASH, and possibly HLS.
    */
    'additional_transcoding_parameters' => [
        '-dn', '-map', '-0:t?', '-sn'
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloud Storage Helper
    |--------------------------------------------------------------------------
    |
    | Helper for managing cloud storage access when transcoding videos.
    |
    */
    'cloud_storage_helper' => App\Helpers\PhpFfmpegVideoStreaming\S3Helper::class,

    /*
    |--------------------------------------------------------------------------
    | CDN Helper
    |--------------------------------------------------------------------------
    |
    | Helper for creating CDN invalidations.
    |
    */
    'cdn_helper' => App\Helpers\CloudFrontHelper::class,

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
    'signing_keypair' => env('TRANSMORPHER_SIGNING_KEYPAIR'),

    /*
    |--------------------------------------------------------------------------
    | Media Handlers
    |--------------------------------------------------------------------------
    |
    | The classes which are responsible for handling media specific actions, such as validation rules or handling the newly saved file.
    |
    */
    'media_handlers' => [
        'image' => App\Classes\MediaHandler\ImageHandler::class,
        'video' => App\Classes\MediaHandler\VideoHandler::class
    ],
];
