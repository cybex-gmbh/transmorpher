<?php

namespace App\Interfaces;

interface VideoEncoderInterface
{
    public function name(): string;

    /**
     * Returns FFmpeg output parameters for the configured encoder.
     *
     * @param bool $forMp4Fallback
     * @return array
     */
    public function outputParameters(bool $forMp4Fallback = false): array;

    public function streamingCodec(): string;
}

