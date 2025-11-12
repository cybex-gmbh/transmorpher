<?php

namespace App\Classes\Intervention;

use App\Exceptions\ImageTransformationException;
use App\Interfaces\ConvertedImageInterface;
use App\Interfaces\ConvertInterface;
use ImagickException;
use Intervention\Image\FileExtension;
use Intervention\Image\Image;
use Intervention\Image\Laravel\Facades\Image as ImageManager;

class Convert implements ConvertInterface
{
    /**
     * Encode to specified format and, if possible, set quality.
     *
     * @param string|Image $image
     * @param string $format
     * @param int $quality
     *
     * @return ConvertedImageInterface
     * @throws ImageTransformationException|ImagickException
     */
    public function encode(string|Image $image, string $format, int $quality = 100): ConvertedImageInterface
    {
        try {
            $convertedImage = ImageManager::read($image)->encodeByExtension(FileExtension::from($format), quality: $quality);
        } catch (ImagickException $exception) {
            $this->handleImagickException($exception);
        }

        return ConvertedImage::createFromString($convertedImage);
    }

    /**
     * @throws ImageTransformationException
     * @throws ImagickException
     */
    protected function handleImagickException(ImagickException $exception)
    {
        if ($exception->getCode() === 1) {
            $customException = new ImageTransformationException($exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        report($customException ?? $exception);
        throw $customException ?? $exception;
    }
}
