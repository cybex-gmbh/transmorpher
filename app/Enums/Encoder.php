<?php

namespace App\Enums;

enum Encoder: string
{
    case CPU_H264 = 'cpu_h264';
    case CPU_HEVC = 'cpu_hevc';
    case NVIDIA_H264 = 'nvidia_h264';
    case NVIDIA_HEVC = 'nvidia_hevc';

    public function getAdditionalParameters(bool $forMp4Fallback = false): array
    {
        return array_merge(
            $forMp4Fallback ? ['-b:v', config('transmorpher.encoder.bitrate')] : [],
            match ($this) {
                Encoder::NVIDIA_H264 => [
                    '-c:v', 'h264_nvenc',
                    '-preset', config('transmorpher.encoder.nvidia.preset')
                ],
                Encoder::NVIDIA_HEVC => [
                    // Fallback MP4 video should be h264
                    '-c:v', $forMp4Fallback ? 'h264_nvenc' : 'hevc_nvenc',
                    '-preset', config('transmorpher.encoder.nvidia.preset')
                ],
                default => []
            },
            config('transmorpher.additional_transcoding_parameters')
        );
    }

    public function getStreamingCodec(): string
    {
        return match ($this) {
            Encoder::CPU_H264, Encoder::NVIDIA_H264 => 'x264',
            Encoder::CPU_HEVC, Encoder::NVIDIA_HEVC => 'hevc',
        };
    }
}
