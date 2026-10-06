<?php

namespace App\Interfaces;

interface TransformInterface
{
    /**
     * Transform image based on specified transformations.
     *
     * @param string $pathToOriginalImage
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     */
    public function image(string $pathToOriginalImage, ?array $transformations = null): string;

    /**
     * Transform document based on specified transformations.
     * Will first create an image from the document.
     *
     * @param string $pathToOriginalDocument
     * @param array|null $transformations
     *
     * @return string Binary string of the image.
     */
    public function document(string $pathToOriginalDocument, ?array $transformations = null): string;
}
