<?php

namespace App\Interfaces;

use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;

interface TranscodeInterface
{
    /**
     * Creates a job which handles the transcoding of a video.
     *
     * @param string $originalFilePath
     * @param Media $media
     * @param Version $version
     * @param UploadSlot $uploadSlot
     * @return bool
     */
    public function createJob(string $originalFilePath, Media $media, Version $version, UploadSlot $uploadSlot): bool;

    /**
     * Creates a job which handles the transcoding of a video when a version number is updated.
     *
     * @param string $originalFilePath
     * @param Media $media
     * @param Version $version
     * @param UploadSlot $uploadSlot
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     *
     * @return bool
     */
    public function createJobForVersionUpdate(string $originalFilePath, Media $media, Version $version, UploadSlot $uploadSlot, int $oldVersionNumber, bool $wasProcessed): bool;

    /**
     * Inform client package about the transcoding result.
     *
     * @param bool $success
     * @param string $callbackUrl
     * @param string $callbackToken
     * @param User $user
     * @param string $identifier
     * @param int $versionNumber
     * @param string|null $message
     *
     * @return void
     */
    public function callback(bool $success, string $callbackUrl, string $callbackToken, User $user, string $identifier, int $versionNumber, string $message = null): void;
}
