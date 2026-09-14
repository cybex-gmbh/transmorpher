<?php

return [
    'class' => App\Classes\Video\Encoder\CpuHevcEncoder::class,
    'streaming_codec' => 'hevc',
    'streaming_parameters' => [],
    'mp4_parameters' => ['-b:v', env('TRANSMORPHER_VIDEO_ENCODER_BITRATE', '6000k')],
    'parameters' => [
        // Omit data streams (e.g. timecodes). Transcoding sometimes failed when data streams were not omitted.
        '-dn',
        // Omit attachments (e.g. metadata files). Metadata should not be publicly available.
        '-map', '-0:t?',
        // Omit subtitles. Subtitles would need an encoder configuration for DASH and possibly HLS.
        '-sn',
    ],
];
