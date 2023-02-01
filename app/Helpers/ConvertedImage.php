<?php

namespace App\Helpers;

use App\Interfaces\ConvertedImageInterface;

class ConvertedImage implements ConvertedImageInterface
{
    protected string $image;

    /**
     * Creates a new instance of a ConvertedImage.
     */
    public function __construct(string $image)
    {
        $this->image = $image;
    }

    /**
     * Create a new ConvertedImage from a string.
     *
     * @param string $image
     *
     * @return ConvertedImageInterface
     */
    public static function createFromString(string $image): ConvertedImageInterface
    {
        return new static($image);
    }

    /**
     * Get the binary representation of the converted image.
     *
     * @return string
     */
    public function getBinary(): string
    {
        return $this->image;
    }

    /**
     * Get the base64 representation of the converted image.
     *
     * @return string
     */
    public function getBase64(): string
    {
        return base64_encode($this->image);
    }
}
