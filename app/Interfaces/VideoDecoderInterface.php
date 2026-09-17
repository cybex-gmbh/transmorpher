<?php

namespace App\Interfaces;

interface VideoDecoderInterface
{
    public function name(): string;

    /**
     * Returns FFmpeg input parameters for the configured decoder.
     *
     * @return array
     */
    public function inputParameters(): array;
}

