<?php

namespace App\Classes\Intervention;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\Transformation;
use App\Interfaces\TransformInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Laravel\Facades\Image as ImageManager;

class Transform implements TransformInterface
{
    /**
     * Transform image based on specified transformations.
     *
     * @param string $pathToOriginalImage
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

        if (!$transformations) {
            return $imageData;
        }

        $image = ImageManager::read($imageData);

        $width = $transformations[Transformation::WIDTH->value] ?? $image->width();
        $height = $transformations[Transformation::HEIGHT->value] ?? $image->height();
        $format = $transformations[Transformation::FORMAT->value] ?? null;
        $quality = $transformations[Transformation::QUALITY->value] ?? null;

        $image = $image->scaleDown($width, $height);

        if ($format) {
            return ImageFormat::from($format)->getConverter()->encode($image, $format, $quality)->getBinary();
        }

        return $image->encode(new AutoEncoder(quality: $quality))->toString();
    }

    /**
     * @return string[]
     */
    public function getSupportedFormats(): array
    {
        return ImageFormat::getFormats();
    }
}
