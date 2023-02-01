<?php

namespace App\Helpers\InterventionTransmorpher;

use App\Enums\Converter;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Interfaces\TransmorpherInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Image;
use Storage;

class InterventionTransmorpher implements TransmorpherInterface
{
    /**
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
            return $this->format($image->stream(), $format, $quality);
        }

        return $image->stream();
    }

    /**
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
     * @param          $image
     * @param string   $format
     * @param int|null $quality
     *
     * @return Image|string
     */
    public function format($image, string $format, int $quality = null): Image|string
    {
        return Converter::from($format)->getConverter()->encode($image, $format, $quality);
    }

    /**
     * @return string[]
     */
    public function getSupportedFormats(): array
    {
        return [
            'jpg',
            'png',
            'gif',
            'webp',
        ];
    }
}
