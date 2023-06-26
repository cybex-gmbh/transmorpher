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
     * @param Media $media
     * @param Version $version
     *
     * @return ResponseState
     */
    public function handleSavedFile(string $basePath, UploadSlot $uploadSlot, string $filePath, Media $media, Version $version): ResponseState
    {
        // Only delete for image, since the UploadSlot will be needed inside the transcoding job.
        $uploadSlot->delete();

        return $this->invalidateCdnCache($basePath) ? ResponseState::IMAGE_UPLOAD_SUCCESSFUL : ResponseState::CDN_INVALIDATION_FAILED;
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
     * @param Media $media
     * @param Version $version
     * @param int $oldVersionNumber
     * @param bool $wasProcessed
     * @param string $callbackUrl
     * @return array
     */
    public function setVersion(User $user, Media $media, Version $version, int $oldVersionNumber, bool $wasProcessed, string $callbackUrl): array
    {
        // Token and valid_until will be set in the 'saving' event.
        // By creating an upload slot, a currently active upload will be canceled.
        $uploadSlot = $user->UploadSlots()->withoutGlobalScopes()->updateOrCreate(['identifier' => $media->identifier], ['media_type' => MediaType::IMAGE]);

        if ($this->invalidateCdnCache(FilePathHelper::toBaseDirectory($media))) {
            // Might instead move the directory to keep derivatives, but S3 can't move directories and each file would have to be moved individually.
            $media->type->handler()->getDerivativesDisk()->deleteDirectory(FilePathHelper::toImageDerivativeVersionDirectory($media, $oldVersionNumber));
            $responseState = ResponseState::VERSION_SET;
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
}
