<?php

namespace App\Classes\MediaHandler;

use App\Enums\ImageFormat;
use App\Enums\ResponseState;
use App\Helpers\Upload;
use App\Interfaces\MediaHandlerInterface;
use App\Models\Media;
use App\Models\UploadSlot;
use App\Models\User;
use App\Models\Version;
use CdnHelper;
use FilePathHelper;
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
        $responseState = $this->invalidateCdnCache($basePath);

        // Only delete for image, since the UploadSlot will be needed inside the transcoding job.
        $uploadSlot->delete();

        return $responseState ?? ResponseState::IMAGE_UPLOAD_SUCCESSFUL;
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
     * @return ResponseState|null
     */
    public function invalidateCdnCache(string $basePath): ResponseState|null
    {
        if (CdnHelper::isConfigured()) {
            try {
                CdnHelper::invalidateImage($basePath);
            } catch (Throwable) {
                $responseState = ResponseState::CDN_INVALIDATION_FAILED;
            }
        }

        return $responseState ?? null;
    }

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
    public function setVersion(User $user, string $identifier, Media $media, Version $version, int $oldVersionNumber, bool $wasProcessed, string $callbackUrl): array
    {
        $uploadSlot = Upload::createUploadSlot($user, $identifier);
        $responseState = $this->invalidateCdnCache(FilePathHelper::toBaseDirectory($user, $identifier));

        if (is_null($responseState)) {
            // Might instead move the directory to keep derivatives, but S3 can't move directories and each file would have to be moved individually.
            $media->type->getDerivativesDisk()->deleteDirectory(FilePathHelper::toImageDerivativeVersionDirectory($user, $identifier, $oldVersionNumber));
        } else {
            $version->update(['number' => $oldVersionNumber]);
        }

        return [
            $responseState ?? ResponseState::VERSION_SET,
            $uploadSlot->token
        ];
    }
}
