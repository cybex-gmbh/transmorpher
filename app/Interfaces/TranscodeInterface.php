<?php

namespace App\Interfaces;

use App\Models\Media;
use App\Models\Version;

interface TranscodeInterface
{
    /**
     * Creates a job which handles the transcoding of a video.
     *
     * @param string     $originalFilePath
     * @param Media      $media
     * @param Version    $version
     * @param string     $callbackUrl
     * @param string     $idToken
     *
     * @return bool
     */
    public function createJob(string $originalFilePath, Media $media, Version $version, string $callbackUrl, string $idToken): bool;

    /**
     * Inform client package about the transcoding result.
     *
     * @param bool   $success
     * @param string $callbackUrl
     * @param string $idToken
     * @param string $userName
     * @param string $identifier
     * @param int    $versionNumber
     *
     * @return void
     */
    public function callback(bool $success, string $callbackUrl, string $idToken, string $userName, string $identifier, int $versionNumber): void;
}
