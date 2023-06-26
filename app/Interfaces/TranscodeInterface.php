<?php

namespace App\Interfaces;

use App\Enums\ResponseState;
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
     * @param ResponseState $responseState
     * @param string $callbackUrl
     * @param string $uploadToken
     * @param User $user
     * @param Media $media
     * @param int $versionNumber
     *
     * @return void
     */
    public function callback(ResponseState $responseState, string $callbackUrl, string $uploadToken, User $user, Media $media, int $versionNumber): void;
}
