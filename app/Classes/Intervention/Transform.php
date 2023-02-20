<?php

namespace App\Classes\Intervention;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Interfaces\ConvertedImageInterface;
use App\Interfaces\TransformInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use InterventionImage;

class Transform implements TransformInterface
{
    /**
     * Transmorph image based on specified transformations.
     *
     * @param string     $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     * @throws FileNotFoundException
     */
    public function transform(string $pathToOriginalImage, array $transformations = null): string
    {
        $disk = MediaStorage::ORIGINALS->getDisk();

        if (!$disk->exists($pathToOriginalImage)) {
            throw new FileNotFoundException(sprintf('File not found at path "%s" on configured disk', $pathToOriginalImage));
        }

        $imageData = $disk->get($pathToOriginalImage);
        $image     = InterventionImage::make($imageData);

        if (!$transformations) {
            return $imageData;
        }

        $width   = $transformations[Transformation::WIDTH->value] ?? $image->getWidth();
        $height  = $transformations[Transformation::HEIGHT->value] ?? $image->getHeight();
        $format  = $transformations[Transformation::FORMAT->value] ?? null;
        $quality = $transformations[Transformation::QUALITY->value] ?? null;

        $image = $this->resize($image, $width, $height);

        if ($format) {
            return $this->format($image->stream(), $format, $quality)->getBinary();
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
        return ImageFormat::from($format)->getConverter()->encode($image, $format, $quality);
    }

    /**
     * @return string[]
     */
    public function getSupportedFormats(): array
    {
        return ImageFormat::getFormats();
    }
}
