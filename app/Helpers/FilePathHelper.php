<?php

namespace App\Helpers;

use App\Enums\Transformation;
use App\Models\Media;

class FilePathHelper
{
    /**
     * Get the path to an (existing) image derivative.
     * If no version number is given, the path to the current version will be returned.
     * Path structure: {username}/{identifier}/{versionNumber}/{width}x_{height}y_{quality}q_{derivativeHash}.{format}
     *
     * @param Media $media
     * @param array|null $transformations
     * @param int|null $versionNumber
     * @return string
     */
    public function toImageDerivativeFile(Media $media, int $versionNumber = null, array $transformations = null): string
    {
        $mediaVersions = $media->Versions();
        $versionNumber ??= $mediaVersions->whereProcessed(true)->max('number');
        $originalFileExtension = pathinfo($mediaVersions->whereNumber($versionNumber)->firstOrFail()->filename, PATHINFO_EXTENSION);

        // Hash of transformation parameters and version number to identify already generated derivatives.
        $derivativeHash = hash('sha256', json_encode($transformations) . $versionNumber);

        return sprintf('%s/%sx_%sy_%sq_%s.%s',
            $this->toImageDerivativeVersionDirectory($media, $versionNumber),
            $transformations[Transformation::WIDTH->value] ?? '',
            $transformations[Transformation::HEIGHT->value] ?? '',
            $transformations[Transformation::QUALITY->value] ?? '',
            $derivativeHash,
            $transformations[Transformation::FORMAT->value] ?? $originalFileExtension,
        );
    }

    /**
     * Get the path to the directory of an image derivative version.
     * Path structure: {username}/{identifier}/{versionNumber}
     *
     * @param Media $media
     * @param int $versionNumber
     *
     * @return string
     */
    public function toImageDerivativeVersionDirectory(Media $media, int $versionNumber): string
    {
        return sprintf('%s/%d', $this->toBaseDirectory($media), $versionNumber);
    }

    /**
     * Get the path to an original.
     * Path structure: {username}/{identifier}/{filename}
     *
     * @param Media $media
     * @param int|null $versionNumber
     *
     * @return string
     */
    public function toOriginalFile(Media $media, int $versionNumber = null): string
    {
        $mediaVersions = $media->Versions();

        // Get the version for either the specified number or for the current version number.
        $version = $versionNumber ? $mediaVersions->whereNumber($versionNumber)->firstOrFail() : $mediaVersions->whereNumber($mediaVersions->whereProcessed(true)->max('number'))->firstOrFail();

        return sprintf('%s/%s', $this->toBaseDirectory($media), $version->filename);
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
     * Path structure: {username}/{identifier}/{format}/{filename}
     *
     * @param Media $media
     * @param int $versionNumber
     * @param string $format
     * @param string|null $fileName
     *
     * @return string
     */
    public function toTempVideoDerivativeFile(Media $media, int $versionNumber, string $format, string $fileName = null): string
    {
        return sprintf('%s/%s/%s', $this->toTempVideoDerivativesDirectory($media, $versionNumber), $format, $fileName ?? 'video');
    }

    /**
     * Get the path to a video derivative.
     * Path structure: {username}/{identifier}/{format}/{filename}
     *
     * @param Media $media
     * @param int $versionNumber
     *
     * @return string
     */
    public function toTempVideoDerivativesDirectory(Media $media, int $versionNumber): string
    {
        return sprintf('%s-%d-temp', $this->toBaseDirectory($media), $versionNumber);
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
     * Filename structure: {versionNumber}-{filename}
     *
     * @param int    $versionNumber
     * @param string $fileName
     *
     * @return string
     */
    public function createOriginalFileName(int $versionNumber, string $fileName): string
    {
        return sprintf('%d-%s', $versionNumber, trim($fileName));
    }
}
