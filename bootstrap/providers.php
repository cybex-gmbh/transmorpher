<?php

return [
    App\Providers\AppServiceProvider::class,

    /*
     * Package Service Providers...
     */
    Intervention\Image\ImageServiceProvider::class,

    /*
     * Application Service Providers...
     */
    App\Providers\CdnHelperServiceProvider::class,
    App\Providers\MediaHandlerServiceProvider::class,
    App\Providers\TranscodeServiceProvider::class,
    App\Providers\TransformServiceProvider::class,
    App\Providers\UploadHandlerServiceProvider::class,
];
