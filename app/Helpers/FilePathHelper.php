<?php

namespace App\Helpers;

use App\Enums\Transformation;
use App\Models\User;

class FilePathHelper
{
    /**
     * Get the path to an (existing) image derivative.
     * Path structure: derivatives/images/{username}/{identifier}/{versionNumber}/{width}x_{height}y_{quality}q_{derivativeHash}.{format}
     *
     * @param User       $user
     * @param string     $transformations
     * @param string     $identifier
     * @param array|null $transformationsArray
     *
     * @return string
     */
    public function getImageDerivativePath(User $user, string $transformations, string $identifier, array $transformationsArray = null): string
    {
        $media                 = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $mediaVersions         = $media->Versions();
        $currentVersionNumber  = $mediaVersions->max('number');
        $currentVersion        = $mediaVersions->whereNumber($currentVersionNumber)->first();
        $originalFileExtension = pathinfo($currentVersion->filename, PATHINFO_EXTENSION);

        // Hash of transformation parameters and current version number to identify already generated derivatives.
        $derivativeHash = hash('sha256', $transformations . $currentVersionNumber);

        return sprintf('derivatives/images/%s/%s/%d/%sx_%sy_%sq_%s.%s',
            $user->name,
            $identifier,
            $currentVersionNumber,
            $transformationsArray[Transformation::WIDTH->value] ?? '',
            $transformationsArray[Transformation::HEIGHT->value] ?? '',
            $transformationsArray[Transformation::QUALITY->value] ?? '',
            $derivativeHash,
            $transformationsArray[Transformation::FORMAT->value] ?? $originalFileExtension,
        );
    }

    /**
     * Get the path to an original image.
     * Path structure: originals/{username}/{identifier}/{filename}
     *
     * @param User   $user
     * @param string $identifier
     *
     * @return string
     */
    public function getImageOriginalPath(User $user, string $identifier): string
    {
        $media                = $user->Media()->whereIdentifier($identifier)->firstOrFail();
        $mediaVersions        = $media->Versions();
        $currentVersionNumber = $mediaVersions->max('number');
        $currentVersion       = $mediaVersions->whereNumber($currentVersionNumber)->first();

        return sprintf('%s/%s', $this->getOriginalsBasePath($user, $identifier), $currentVersion->filename);
    }

    /**
     * Get the base path for original media.
     * Path structure: originals/{username}/{identifier}/
     *
     * @param User   $user
     * @param string $identifier
     *
     * @return string
     */
    public function getOriginalsBasePath(User $user, string $identifier): string
    {
        return sprintf('originals/%s/%s', $user->name, $identifier);
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
