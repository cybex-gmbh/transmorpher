<?php

namespace App\Interfaces;

interface ConvertInterface
{
    /**
     * Encode to specified format and if possible set quality.
     *
     * @param string   $image Binary string of the image.
     * @param string   $format
     * @param int|null $quality
     *
     * @return ConvertedImageInterface
     */
    public function encode(string $image, string $format, ?int $quality = null): ConvertedImageInterface;
}
