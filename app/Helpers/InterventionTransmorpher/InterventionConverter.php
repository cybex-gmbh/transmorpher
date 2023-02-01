<?php

namespace App\Helpers\InterventionTransmorpher;

use App\Helpers\ConvertedImage;
use App\Interfaces\ConvertedImageInterface;
use App\Interfaces\ConverterInterface;
use Image;

class InterventionConverter implements ConverterInterface
{
    /**
     * Encode to specified format and if possible set quality.
     *
     * @param string|Image $image
     * @param string       $format
     * @param int|null     $quality
     */
    public function encode(string|Image $image, string $format, int $quality = null): ConvertedImageInterface
    {
        $convertedImage = Image::make($image)->encode($format, $quality);

        return ConvertedImage::createFromString($convertedImage);
    }
}
