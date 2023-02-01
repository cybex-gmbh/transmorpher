<?php

namespace App\Helpers\InterventionTransmorpher;

use App\Enums\Converter;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Interfaces\ConvertedImageInterface;
use App\Interfaces\TransmorpherInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Image;
use Storage;

class InterventionTransmorpher implements TransmorpherInterface
{
    /**
     * Transmorph image based on specified transformations.
     *
     * @param string     $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string
     * @throws FileNotFoundException
     */
    public function transmorph(string $pathToOriginalImage, array $transformations = null): string
    {
        $disk = MediaStorage::ORIGINALS->getDisk();

        if (!$disk->exists($pathToOriginalImage)) {
            throw new FileNotFoundException(sprintf('File not found at path "%s" on configured disk', $pathToOriginalImage));
        }

        $imageData = $disk->get($pathToOriginalImage);
        $image     = Image::make($imageData);

        if (!$transformations) {
            return $imageData;
        }

        $width   = $transformations[Transformation::WIDTH->value] ?? $image->getWidth();
        $height  = $transformations[Transformation::HEIGHT->value] ?? $image->getHeight();
        $format  = $transformations[Transformation::FORMAT->value] ?? null;
        $quality = $transformations[Transformation::QUALITY->value] ?? null;

        $image = $this->resize($image, $width, $height);

        if ($format) {
            $this->format($image->stream(), $format, $quality)->getBinary();
        }

        return $image->stream();
    }

    /**
     * Resize an image based on specified width and height.
     * Keeps the aspect ratio and prevents upsizing.
     *
     * @param     $image
     * @param int $width
     * @param int $height
     */
    public function resize($image, int $width, int $height)
    {
        return $image->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
    }

    /**
     * Use a converter class to encode the image to given format and quality.
     *
     * @param          $image
     * @param string   $format
     * @param int|null $quality
     *
     * @return ConvertedImageInterface
     */
    public function format($image, string $format, int $quality = null): ConvertedImageInterface
    {
        return Converter::from($format)->getConverter()->encode($image, $format, $quality);
    }

    /**
     * @return string[]
     */
    public function getSupportedFormats(): array
    {
        return Converter::getMimeTypes();
    }
}
