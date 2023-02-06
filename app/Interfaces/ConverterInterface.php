<?php

namespace App\Interfaces;

interface ConverterInterface
{
    /**
     * Encode to specified format and if possible set quality.
     *
     * @param string   $image Binary string of the image.
     * @param string   $format
     * @param int|null $quality
     */
    public function encode(string $image, string $format, int $quality = null): ConvertedImageInterface;
}
