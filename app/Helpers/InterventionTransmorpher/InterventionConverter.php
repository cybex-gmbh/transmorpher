<?php

namespace App\Helpers\InterventionTransmorpher;

use App\Interfaces\ConverterInterface;
use Intervention\Image\Image;

class InterventionConverter implements ConverterInterface
{
    /**
     * @param string|Image $image
     * @param string       $format
     * @param int|null     $quality
     *
     * @return Image
     */
    public function encode(string|Image $image, string $format, int $quality = null): Image
    {
        if ($image instanceof Image) {
            return $image->encode($format, $quality);
        }

        return \Intervention\Image\Facades\Image::make($image)->encode($format, $quality);
    }
}
