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
    | Storage Disks
    |--------------------------------------------------------------------------
    |
    | The disks on which the Transmorpher operates.
    |
    */
    'disks' => [
        'originals' => env('TRANSMORPHER_DISK_ORIGINALS', 'localOriginals'),
        'imageDerivatives' => env('TRANSMORPHER_DISK_IMAGE_DERIVATIVES', 'localImageDerivatives'),
        'documentDerivatives' => env('TRANSMORPHER_DISK_DOCUMENT_DERIVATIVES', 'localDocumentDerivatives'),
        'videoDerivatives' => env('TRANSMORPHER_DISK_VIDEO_DERIVATIVES', 'localVideoDerivatives'),
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
    | CDN Class
    |--------------------------------------------------------------------------
    |
    | The class which is used for creating CDN invalidations.
    | The class must implement CdnHelperInterface.
    |
    | These are defined through the `config/transmorpher/classes/cdn` files.
    |
    | Available classes:
    | - cloudfront (AWS CloudFront)
    |
    */
    'cdn_class' => env('TRANSMORPHER_CDN', 'cloudfront'),

    /*
    |--------------------------------------------------------------------------
    | Upload Handler
    |--------------------------------------------------------------------------
    |
    | The upload handler used for the v2 upload flow.
    | The class must implement UploadHandlerContract.
    |
    | These are defined through the `config/transmorpher/class/handler/upload` files.
    |
    | You can choose from:
    | - default       (uses pionl/laravel-chunk-upload, can use any Laravel disk)
    | - s3-multi-part (S3 multipart uploads via pre-signed URLs, originals disk needs to be an S3 disk)
    |
    */
    'upload_handler' => env('TRANSMORPHER_UPLOAD_HANDLER', 'default'),
];
