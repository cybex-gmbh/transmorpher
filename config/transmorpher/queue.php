<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue Names and Connections
    |--------------------------------------------------------------------------
    |
    | Configure queue names and connections for each Transmorpher queue.
    | All connections fall back to the QUEUE_CONNECTION when not set.
    |
    | To use SQS FIFO, append .fifo to the queue name:
    |   TRANSMORPHER_VIDEO_TRANSCODING_QUEUE=video-transcoding.fifo
    |   TRANSMORPHER_VIDEO_TRANSCODING_CONNECTION=sqs
    |
    | Workers must listen on the same queue name and connection, e.g. in compose.yml:
    |   QUEUE: ${TRANSMORPHER_VIDEO_TRANSCODING_QUEUE:-video-transcoding}
    |   QUEUE_CONNECTION: ${TRANSMORPHER_VIDEO_TRANSCODING_QUEUE_CONNECTION:-${QUEUE_CONNECTION}}
    |
    */

    'video_transcoding' => [
        'queue' => env('TRANSMORPHER_VIDEO_TRANSCODING_QUEUE', 'video-transcoding'),
        'connection' => env('TRANSMORPHER_VIDEO_TRANSCODING_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
    ],

    'client_notifications' => [
        'queue' => env('TRANSMORPHER_CLIENT_NOTIFICATIONS_QUEUE', 'client-notifications'),
        'connection' => env('TRANSMORPHER_CLIENT_NOTIFICATIONS_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
    ],

    'email' => [
        'queue' => env('TRANSMORPHER_EMAIL_QUEUE', 'email'),
        'connection' => env('TRANSMORPHER_EMAIL_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
    ],

];
