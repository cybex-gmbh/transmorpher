<?php

namespace App\Enums;

enum Decoder: string
{
    //
    case CPU = 'cpu';
    case NVIDIA_CUDA = 'nvidia_cuda';

    public function getInitialParameters(bool $forMp4Fallback = false): array
    {
        return array_merge(
            match ($this) {
                Decoder::NVIDIA_CUDA => [
                    '-hwaccel', 'cuda',
                    '-hwaccel_output_format','cuda',
                    '-extra_hw_frames', config('transmorpher.decoder.nvidia.hwFrames'),
                ],
                default => []
            },
            config('transmorpher.initial_transcoding_parameters')
        );
    }
}
