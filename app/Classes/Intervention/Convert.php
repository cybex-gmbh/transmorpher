<?php

namespace App\Classes\Intervention;

use App\Interfaces\ConvertedImageInterface;
use App\Interfaces\ConvertInterface;
use InterventionImage;

class Convert implements ConvertInterface
{
    /**
     * Encode to specified format and if possible set quality.
     *
     * @param string|InterventionImage $image
     * @param string                   $format
     * @param int|null                 $quality
     *
     * @return ConvertedImageInterface
     */
    public function encode(string|InterventionImage $image, string $format, ?int $quality = null): ConvertedImageInterface
    {
        $convertedImage = InterventionImage::make($image)->encode($format, $quality);

        return ConvertedImage::createFromString($convertedImage);
    }
}
