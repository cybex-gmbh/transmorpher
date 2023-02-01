<?php

namespace App\Helpers\InterventionTransmorpher;

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
    public function encode(string|Image $image, string $format, int $quality = null)
    {
        $image = ($image instanceof Image ? $image : Image::make($image));

        return $image->encode($format, $quality);
    }
}
