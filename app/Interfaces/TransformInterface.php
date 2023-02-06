<?php

namespace App\Interfaces;

interface TransformInterface
{
    /**
     * Transmorph image based on specified transformations.
     *
     * @param string     $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string
     */
    public function transmorph(string $pathToOriginalImage, array $transformations = null): string;

    /**
     * Resize an image based on specified width and height.
     *
     * @param     $image
     * @param int $width
     * @param int $height
     */
    public function resize($image, int $width, int $height);

    /**
     * Use a converter class to encode the image to given format and quality.
     *
     * @param          $image
     * @param string   $format
     * @param int|null $quality
     *
     * @return ConvertedImageInterface
     */
    public function format($image, string $format, int $quality = null): ConvertedImageInterface;

    /**
     * @return array
     */
    public function getSupportedFormats(): array;
}
