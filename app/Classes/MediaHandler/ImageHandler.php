<?php

namespace App\Classes\MediaHandler;

use App\Enums\ImageFormat;
use App\Enums\MediaStorage;
use App\Enums\MediaType;
use App\Enums\ResponseState;
use App\Interfaces\MediaHandlerInterface;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use FilePathHelper;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

class ImageHandler implements MediaHandlerInterface
{
    /**
     * @param string $basePath
     * @param UploadSlot $uploadSlot
     * @param string $filePath
     * @param Version $version
     *
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Version $version): ResponseState
    {
        if ($this->invalidateCdnCache($basePath)) {
            /**
             * This prevents CDN cache pollution.
             *
             * Explanation:
             * 1. new version is uploaded
             * 2. media is requested and new version is delivered
             * 3. cache invalidation fails, version gets deleted
             * 4. now nonexistent version is still in the CDN cache
             */
            $version->update(['processed' => true]);

            return ResponseState::IMAGE_UPLOAD_SUCCESSFUL;
        }

        return ResponseState::CDN_INVALIDATION_FAILED;
    }

    /**
     * @return string
     */
    public function getValidationRules(): string
    {
        return sprintf('mimes:%s', implode(',', ImageFormat::getFormats()));
    }

    /**
     * @param string $basePath
     * @return bool
     */
    public function invalidateCdnCache(string $basePath): bool
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateImage($basePath);
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param User $user
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @param string $callbackUrl
     * @return array
     */
    public function setVersion(User $user, Version $version, int $oldVersionNumber, bool $wasProcessed, string $callbackUrl): array
    {
        // Token and valid_until will be set in the 'saving' event.
        // By creating an upload slot, a currently active upload will be canceled.
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $version->Media->identifier], ['media_type' => MediaType::IMAGE]);

        if ($this->invalidateCdnCache(FilePathHelper::toBaseDirectory($version->Media))) {
            $version->update(['processed' => true]);
            $responseState = ResponseState::IMAGE_VERSION_SET;
        } else {
            $version->update(['number' => $oldVersionNumber]);
            $responseState = ResponseState::CDN_INVALIDATION_FAILED;
        }

        return [
            $responseState,
            $uploadSlot->token
        ];
    }

    /**
     * @return Filesystem
     */
    public function getDerivativesDisk(): Filesystem
    {
        return MediaStorage::IMAGE_DERIVATIVES->getDisk();
    }

    /**
     * @param Media $media
     * @return array
     */
    public function getVersions(Media $media): array
    {
        $processedVersions = $media->Versions()->where('processed', true)->get();
        $currentVersionNumber = $processedVersions->max('number');

        return [
            'currentVersion' => $currentVersionNumber,
            'currentlyProcessedVersion' => $currentVersionNumber,
            'versions' => $processedVersions->pluck('created_at', 'number')->map(fn($date) => strtotime($date)),
        ];
    }
}
