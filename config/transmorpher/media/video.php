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
    | These are defined through the `config/transmorpher/classes/handler/media/video` files.
    |
    | You can choose from:
    | - default
    |
    */
    'handler' => env('TRANSMORPHER_VIDEO_HANDLER', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Transcoder
    |--------------------------------------------------------------------------
    |
    | The class which is used for transcoding videos.
    | The class must implement TranscodeInterface.
    |
    | These are defined through the `config/transmorpher/classes/video/transcoder` files.
    |
    | Available transcoders:
    | - default (uses FFmpeg and Laravel Queue for transcoding)
    |
    */
    'transcoder' => env('TRANSMORPHER_VIDEO_TRANSCODER', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Decoder
    |--------------------------------------------------------------------------
    |
    | The decoder used when transcoding videos.
    | These are defined through the `config/media/video/decoder` files.
    |
    | You can choose from:
    | cpu, nvidia-cuda
    */
    'decoder' => env('TRANSMORPHER_VIDEO_DECODER', 'cpu'),

    /*
    |--------------------------------------------------------------------------
    | Encoder
    |--------------------------------------------------------------------------
    |
    | The encoder used when transcoding videos.
    | Additional FFmpeg parameters are controlled through the according `config/media/video/encoder` files.
    |
    | You can choose from:
    | cpu-h264, cpu-hevc, nvidia-h264, nvidia-hevc
    */
    'encoder' => env('TRANSMORPHER_VIDEO_ENCODER', 'cpu-h264'),

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
    'representations' => explode(',', env('TRANSMORPHER_VIDEO_REPRESENTATIONS', '360,480,720,1080,1440,2160')),
];
