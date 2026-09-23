<?php

namespace App\Interfaces;

interface ConvertedImageInterface
{
    /**
     * Create a new ConvertedImage from a string.
     *
     * @param string $image
     *
     * @return ConvertedImageInterface
     */
    public static function createFromString(string $image): self;

    /**
     * Get the binary representation of the converted image.
     *
     * @return string
     */
    public function getBinary(): string;

    /**
     * Get the base64 representation of the converted image.
     *
     * @return string
     */
    public function getBase64(): string;
}
