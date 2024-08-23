<?php

namespace App\Enums;

enum Encoder: string
{
    case CPU_H264 = 'cpu-h264';
    case CPU_HEVC = 'cpu-hevc';
    case NVIDIA_H264 = 'nvidia-h264';
    case NVIDIA_HEVC = 'nvidia-hevc';

    public function getAdditionalParameters(bool $forMp4Fallback = false): array
    {
        $configuredParameters = config(sprintf('encoder.%s', $this->value), []);

        if ($forMp4Fallback) {
            $enumParameters = match ($this) {
                Encoder::NVIDIA_H264, Encoder::NVIDIA_HEVC => ['-c:v', 'h264_nvenc', '-b:v', env('TRANSMORPHER_VIDEO_ENCODER_BITRATE', '6000k')],
                default => ['-b:v', env('TRANSMORPHER_VIDEO_ENCODER_BITRATE', '6000k')],
            };
        } else {
            $enumParameters = match ($this) {
                Encoder::NVIDIA_H264 => ['-c:v', 'h264_nvenc'],
                Encoder::NVIDIA_HEVC => ['-c:v', 'hevc_nvenc'],
                default => [],
            };
        }

        return array_merge($enumParameters, $configuredParameters);
    }

    public function getStreamingCodec(): string
    {
        return match ($this) {
            Encoder::CPU_H264, Encoder::NVIDIA_H264 => 'x264',
            Encoder::CPU_HEVC, Encoder::NVIDIA_HEVC => 'hevc',
        };
    }
}
