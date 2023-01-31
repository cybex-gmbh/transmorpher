<?php

namespace App\Helpers\InterventionTransmorpher;

use App\Interfaces\ConverterInterface;
use Image;

class InterventionConverter implements ConverterInterface
{
    /**
     * @param string|Image $image
     * @param string       $format
     * @param int|null     $quality
     */
    public function encode(string|Image $image, string $format, int $quality = null)
    {
        if ($image instanceof Image) {
            return $image->encode($format, $quality);
        }

        return Image::make($image)->encode($format, $quality);
    }
}
