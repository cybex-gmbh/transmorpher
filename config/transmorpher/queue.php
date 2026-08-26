<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Names and Connections
    |--------------------------------------------------------------------------
    |
    | Configure queue names and connections for each Transmorpher queue.
    | All connections fall back to the QUEUE_CONNECTION when not set.
    | When "use_sqs_fifo" is true, queue names will automatically be suffixed with ".fifo".
    |
    */

    'video_transcoding' => [
        'queue' => env('TRANSMORPHER_VIDEO_TRANSCODING_QUEUE', 'video-transcoding'),
        'connection' => env('TRANSMORPHER_VIDEO_TRANSCODING_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
        'use_sqs_fifo' => env('TRANSMORPHER_VIDEO_TRANSCODING_USE_SQS_FIFO', false),
    ],

    'client_notifications' => [
        'queue' => env('TRANSMORPHER_CLIENT_NOTIFICATIONS_QUEUE', 'client-notifications'),
        'connection' => env('TRANSMORPHER_CLIENT_NOTIFICATIONS_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
        'use_sqs_fifo' => env('TRANSMORPHER_CLIENT_NOTIFICATIONS_USE_SQS_FIFO', false),
    ],

    'email' => [
        'queue' => env('TRANSMORPHER_EMAIL_QUEUE', 'email'),
        'connection' => env('TRANSMORPHER_EMAIL_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
        'use_sqs_fifo' => env('TRANSMORPHER_EMAIL_USE_SQS_FIFO', false),
    ],

];
