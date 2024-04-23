<?php

namespace App\Interfaces;

use App\Enums\MediaStorage;
use App\Enums\ResponseState;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use Illuminate\Contracts\Filesystem\Filesystem;

interface MediaHandlerInterface
{
    /**
     * @param string $basePath
     * @param UploadSlot $uploadSlot
     * @param Version $version
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, Version $version): ResponseState;

    /**
     * @return string
     */
    public function getValidationRules(): string;

    /**
     * @param string $basePath
     * @return bool
     */
    public function invalidateCdnCache(string $basePath): bool;

    /**
     * @param User $user
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @return array
     */
    public function setVersion(User $user, Version $version, int $oldVersionNumber, bool $wasProcessed): array;

    /**
     * @return Filesystem
     */
    public function getDerivativesDisk(): Filesystem;
}
