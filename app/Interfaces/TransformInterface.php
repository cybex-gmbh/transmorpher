<?php

namespace App\Interfaces;

interface TransformInterface
{
    /**
     * Transmorph image based on specified transformations.
     *
     * @param string $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     */
    public function transform(string $pathToOriginalImage, array $transformations = null): string;
}
