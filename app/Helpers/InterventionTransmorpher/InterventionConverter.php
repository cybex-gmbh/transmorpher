<?php

namespace App\Helpers\InterventionTransmorpher;

use App\Helpers\ConvertedImage;
use App\Interfaces\ConvertedImageInterface;
use App\Interfaces\ConverterInterface;
use InterventionImage;

class InterventionConverter implements ConverterInterface
{
    /**
     * Encode to specified format and if possible set quality.
     *
     * @param string|InterventionImage $image
     * @param string                   $format
     * @param int|null                 $quality
     */
    public function encode(string|InterventionImage $image, string $format, int $quality = null): ConvertedImageInterface
    {
        $convertedImage = InterventionImage::make($image)->encode($format, $quality);

        return ConvertedImage::createFromString($convertedImage);
    }
}
