<?php

namespace App\Helpers;

use App\Enums\Transformation;
use App\Models\Media;
use App\Models\Version;

class FilePathHelper
{
    /**
     * Get the path to an (existing) image derivative.
     * Path structure: {username}/{identifier}/{versionKey}/{width}x_{height}y_{quality}q_{derivativeHash}.{format}
     *
     * @param Version $version
     * @param array|null $transformations
     * @return string
     */
    public function toImageDerivativeFile(Version $version, array $transformations = null): string
    {
        $originalFileExtension = pathinfo($version->filename, PATHINFO_EXTENSION);

        // Hash of transformation parameters and version number to identify already generated derivatives.
        $derivativeHash = hash('sha256', json_encode($transformations) . $version->getKey());

        return sprintf('%s/%sx_%sy_%sq_%s.%s',
            $this->toImageDerivativeVersionDirectory($version),
            $transformations[Transformation::WIDTH->value] ?? '',
            $transformations[Transformation::HEIGHT->value] ?? '',
            $transformations[Transformation::QUALITY->value] ?? '',
            $derivativeHash,
            $transformations[Transformation::FORMAT->value] ?? $originalFileExtension,
        );
    }

    /**
     * Get the path to the directory of an image derivative version.
     * Path structure: {username}/{identifier}/{versionKey}
     *
     * @param Version $version
     * @return string
     */
    public function toImageDerivativeVersionDirectory(Version $version): string
    {
        return sprintf('%s/%s', $this->toBaseDirectory($version->Media), $version->getKey());
    }

    /**
     * Get the path to an original.
     * Path structure: {username}/{identifier}/{filename}
     *
     * @param Version $version
     * @return string
     */
    public function toOriginalFile(Version $version): string
    {
        return sprintf('%s/%s', $this->toBaseDirectory($version->Media), $version->filename);
    }

    /**
     * Get the path to a video derivative.
     * Path structure: {username}/{identifier}/{format}/{filename}
     *
     * @param Media $media
     * @param string $format
     * @param string|null $fileName
     *
     * @return string
     */
    public function toVideoDerivativeFile(Media $media, string $format, string $fileName = null): string
    {
        return sprintf('%s/%s/%s', $this->toBaseDirectory($media), $format, $fileName ?? 'video');
    }

    /**
     * Get the path to a temporary video derivative.
     * Path structure: {username}/{identifier}-{versionKey}-temp/{format}/{filename}
     *
     * @param Version $version
     * @param string $format
     * @param string|null $fileName
     *
     * @return string
     */
    public function toTempVideoDerivativeFile(Version $version, string $format, string $fileName = null): string
    {
        return sprintf('%s/%s/%s', $this->toTempVideoDerivativesDirectory($version), $format, $fileName ?? 'video');
    }

    /**
     * Get the path to the temporary video derivatives directory.
     * Path structure: {username}/{identifier}-{versionKey}-temp
     *
     * @param Version $version
     * @return string
     */
    public function toTempVideoDerivativesDirectory(Version $version): string
    {
        return sprintf('%s-%s-temp', $this->toBaseDirectory($version->Media), $version->getKey());
    }

    /**
     * Get the base path for media.
     * Path structure: {username}/{identifier}/
     *
     * @param Media $media
     * @return string
     */
    public function toBaseDirectory(Media $media): string
    {
        return sprintf('%s/%s', $media->User->name, $media->identifier);
    }

    /**
     * Create the filename for an original.
     * Filename structure: {versionKey}-{filename}
     *
     * @param Version $version
     * @param string $fileName
     *
     * @return string
     */
    public function createOriginalFileName(Version $version, string $fileName): string
    {
        return sprintf('%s-%s', $version->getKey(), trim($fileName));
    }
}
