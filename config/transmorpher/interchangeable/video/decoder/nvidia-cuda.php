<?php

return [
    'class' => App\Classes\Video\Decoder\NvidiaCudaDecoder::class,
    'parameters' => [
        '-hwaccel', 'cuda',
        '-hwaccel_output_format', 'cuda',
        '-extra_hw_frames', env('TRANSMORPHER_VIDEO_DECODER_NVIDIA_HWFRAMES', 10),
    ],
];
