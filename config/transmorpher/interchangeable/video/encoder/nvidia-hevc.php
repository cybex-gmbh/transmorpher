<?php

return [
    'class' => App\Classes\Video\Encoder\NvidiaHevcEncoder::class,
    'streaming_codec' => 'hevc',
    'streaming_parameters' => ['-c:v', 'hevc_nvenc'],
    'mp4_parameters' => ['-c:v', 'h264_nvenc', '-b:v', env('TRANSMORPHER_VIDEO_ENCODER_BITRATE', '6000k')],
    'parameters' => [
        '-preset', env('TRANSMORPHER_VIDEO_ENCODER_NVIDIA_PRESET', 'p4'),
        // Omit data streams (e.g. timecodes). Transcoding sometimes failed when data streams were not omitted.
        '-dn',
        // Omit attachments (e.g. metadata files). Metadata should not be publicly available.
        '-map', '-0:t?',
        // Omit subtitles. Subtitles would need an encoder configuration for DASH and possibly HLS.
        '-sn',
    ],
];
