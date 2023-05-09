<?php

namespace App\Interfaces;

use App\Enums\ResponseState;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;

interface MediaHandlerInterface
{
    /**
     * @param string $basePath
     * @param UploadSlot $uploadSlot
     * @param string $filePath
     * @param Media $media
     * @param Version $version
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Media $media, Version $version): ResponseState;

    /**
     * @return string
     */
    public function getValidationRules(): string;

    /**
     * @param string $basePath
     * @return ResponseState|null
     */
    public function invalidateCdnCache(string $basePath): ResponseState|null;

    /**
     * @param User $user
     * @param string $identifier
     * @param Media $media
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @param string $callbackUrl
     * @return array
     */
    public function setVersion(User $user, string $identifier, Media $media, Version $version, int $oldVersionNumber, bool $wasProcessed, string $callbackUrl): array;
}
