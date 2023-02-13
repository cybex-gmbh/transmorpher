<?php

namespace App\Helpers;

use App\Enums\Transformation;
use App\Models\User;

class FilePathHelper
{
    /**
     * Get the path to an (existing) image derivative.
     * Path structure: {username}/{identifier}/{versionNumber}/{width}x_{height}y_{quality}q_{derivativeHash}.{format}
     *
     * @param User       $user
     * @param string     $transformations
     * @param string     $identifier
     * @param array|null $transformationsArray
     *
     * @return string
     */
    public function getPathToImageDerivative(User $user, string $transformations, string $identifier, array $transformationsArray = null): string
    {
        $media                 = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $mediaVersions         = $media->Versions();
        $currentVersionNumber  = $mediaVersions->max('number');
        $currentVersion        = $mediaVersions->whereNumber($currentVersionNumber)->first();
        $originalFileExtension = pathinfo($currentVersion->filename, PATHINFO_EXTENSION);

        // Hash of transformation parameters and current version number to identify already generated derivatives.
        $derivativeHash = hash('sha256', $transformations . $currentVersionNumber);

        return sprintf('%s/%d/%sx_%sy_%sq_%s.%s',
            $this->getBasePath($user, $identifier),
            $currentVersionNumber,
            $transformationsArray[Transformation::WIDTH->value] ?? '',
            $transformationsArray[Transformation::HEIGHT->value] ?? '',
            $transformationsArray[Transformation::QUALITY->value] ?? '',
            $derivativeHash,
            $transformationsArray[Transformation::FORMAT->value] ?? $originalFileExtension,
        );
    }

    /**
     * Get the path to an original.
     * Path structure: {username}/{identifier}/{filename}
     *
     * @param User     $user
     * @param string   $identifier
     * @param int|null $versionNumber
     *
     * @return string
     */
    public function getPathToOriginal(User $user, string $identifier, int $versionNumber = null): string
    {
        $media         = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $mediaVersions = $media->Versions();

        // Get the version for either the specified number or for the current version number.
        $version = $versionNumber ? $mediaVersions->whereNumber($versionNumber)->first() : $mediaVersions->whereNumber($mediaVersions->max('number'))->first();

        return sprintf('%s/%s', $this->getBasePath($user, $identifier), $version->filename);
    }

    /**
     * Get the path to a video derivative.
     * Path structure: {username}/{identifier}/{format}/{filename}
     *
     * @param User        $user
     * @param string      $identifier
     * @param string      $format
     * @param string|null $fileName
     *
     * @return string
     */
    public function getPathToVideoDerivative(User $user, string $identifier, string $format, string $fileName = null): string
    {
        return sprintf('%s/%s/%s', $this->getBasePath($user, $identifier), $format, $fileName ?? $identifier);
    }

    /**
     * Get the path to a temporary video derivative.
     * Path structure: {username}/{identifier}/{format}/{filename}
     *
     * @param User        $user
     * @param string      $identifier
     * @param int         $versionNumber
     * @param string      $format
     * @param string|null $fileName
     *
     * @return string
     */
    public function getPathToTempVideoDerivative(User $user, string $identifier, int $versionNumber, string $format, string $fileName = null): string
    {
        return sprintf('%s/%s/%s', $this->getBasePathForTempVideoDerivatives($user, $identifier, $versionNumber), $format, $fileName ?? $identifier);
    }

    /**
     * Get the path to a video derivative.
     * Path structure: {username}/{identifier}/{format}/{filename}
     *
     * @param User   $user
     * @param string $identifier
     * @param int    $versionNumber
     *
     * @return string
     */
    public function getBasePathForTempVideoDerivatives(User $user, string $identifier, int $versionNumber): string
    {
        return sprintf('%s-%d-temp', $this->getBasePath($user, $identifier), $versionNumber);
    }

    /**
     * Get the base path for media.
     * Path structure: {username}/{identifier}/
     *
     * @param User   $user
     * @param string $identifier
     *
     * @return string
     */
    public function getBasePath(User $user, string $identifier): string
    {
        return sprintf('%s/%s', $user->name, $identifier);
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
        return sprintf('%d-%s', $versionNumber, $fileName);
    }
}
