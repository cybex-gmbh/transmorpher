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
        return array_merge($forMp4Fallback ?
            match ($this) {
                Encoder::NVIDIA_H264, Encoder::NVIDIA_HEVC => [
                    '-c:v', 'h264_nvenc',
                    '-b:v', env('TRANSMORPHER_VIDEO_ENCODER_BITRATE', '6000k'),
                ],
                default => [
                    '-b:v', env('TRANSMORPHER_VIDEO_ENCODER_BITRATE', '6000k'),
                ],
            } : match ($this) {
                Encoder::NVIDIA_H264 => ['-c:v', 'h264_nvenc'],
                Encoder::NVIDIA_HEVC => ['-c:v', 'hevc_nvenc'],
                default => [],
            },
            config(sprintf('decoder.%s', $this->value)) ?? [],
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
