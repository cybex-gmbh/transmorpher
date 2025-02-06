<?php

namespace App\Classes\Intervention;

use App\Interfaces\ConvertedImageInterface;
use App\Interfaces\ConvertInterface;
use Intervention\Image\FileExtension;
use Intervention\Image\Image;
use Intervention\Image\Laravel\Facades\Image as ImageManager;

class Convert implements ConvertInterface
{
    /**
     * Encode to specified format and if possible, set quality.
     *
     * @param string|Image $image
     * @param string $format
     * @param int|null $quality
     *
     * @return ConvertedImageInterface
     */
    public function encode(string|Image $image, string $format, ?int $quality = null): ConvertedImageInterface
    {
        $convertedImage = ImageManager::read($image)->encodeByExtension(FileExtension::from($format), quality: $quality ?? 100);

        return ConvertedImage::createFromString($convertedImage);
    }
}
